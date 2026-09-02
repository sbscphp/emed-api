<?php

namespace App\Services\Patient;

use App\Enums\GeneralEnums;
use App\Enums\RoleEnums;
use App\Exceptions\PatientAuthException;
use App\Mail\PatientPasswordResetOtpMail;
use App\Models\Patient;
use App\Models\PatientVerificationToken;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Class PatientAuthService
 *
 * Drives the patient mobile app's onboarding and sign in:
 *
 *   1. the patient picks their hospital and types the email or phone number the
 *      hospital registered them with,
 *   2. we look that invitation up and answer with one of three outcomes the app
 *      renders as its own screen — invitation_found, account_exists, not_found,
 *   3. a found invitation hands back a short lived token the "create password"
 *      screen spends,
 *   4. from then on the patient signs in with their password, again choosing
 *      the hospital, because one account can belong to several of them.
 */
class PatientAuthService
{
    /** The invitation we found matches an account that still has to set a password. */
    public const STATUS_INVITATION_FOUND = 'invitation_found';

    /** The patient already finished onboarding — send them to sign in or reset. */
    public const STATUS_ACCOUNT_EXISTS = 'account_exists';

    /** No patient record at this hospital matches what was typed. */
    public const STATUS_NOT_FOUND = 'not_found';

    public function __construct(protected PatientAccountService $patientAccountService) {}

    /**
     * The hospitals that know this patient, for the verification and login
     * screens. Never the full directory: a patient picks from the hospitals
     * they actually attend.
     *
     * Two sources, because a patient reaches this screen at two different
     * points in their life. Normally their account already carries a tenant
     * membership per hospital. But every patient registered before the app
     * existed has a patient record and no account at all, and looking only at
     * memberships would tell them no hospital knows them.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function hospitals(string $identifier): Collection
    {
        $identifier = trim($identifier);

        $user = $this->findUserByIdentifier($identifier);

        $byMembership = $user
            ? $user->tenants()
            ->where('tenants.status', GeneralEnums::ACTIVE->value)
            ->whereNull('tenant_users.deleted_at')
            ->get(['tenants.id', 'tenants.uuid', 'tenants.name', 'tenants.logo', 'tenants.address'])
            : collect();

        $byPatientRecord = $this->hospitalsWithPatientRecord(
            $identifier,
            $byMembership->pluck('id')->all()
        );

        return $byMembership
            ->concat($byPatientRecord)
            ->map(fn($tenant) => [
                'uuid'    => $tenant->uuid,
                'name'    => $tenant->name,
                'logo'    => $tenant->logo,
                'address' => $tenant->address,
            ])
            ->unique('uuid')
            ->sortBy('name')
            ->values();
    }

    /**
     * Hospitals holding a patient record for this email or phone number.
     *
     * Patient records live in each tenant's own database, so this asks them one
     * at a time. Only hospitals not already found through a membership are
     * visited, which leaves this loop empty for every patient who has completed
     * onboarding.
     *
     * @param  array<int, int>  $excludeTenantIds
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    protected function hospitalsWithPatientRecord(string $identifier, array $excludeTenantIds = []): Collection
    {
        if ($identifier === '') {
            return collect();
        }

        $matches = collect();
        $originalDatabase = config('database.connections.tenant.database');

        $tenants = Tenant::query()
            ->where('status', GeneralEnums::ACTIVE->value)
            ->when(!empty($excludeTenantIds), fn($query) => $query->whereNotIn('id', $excludeTenantIds))
            ->get();

        foreach ($tenants as $tenant) {
            try {
                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Queried through the connection rather than the Patient model:
                // the oldest tenant databases have no tenant_id column, and
                // being inside the tenant's own database already scopes this.
                $found = DB::connection('tenant')
                    ->table('patients')
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($identifier) {
                        $query->where('email', $identifier)
                            ->orWhere('phoneno', $identifier);
                    })
                    ->exists();

                if ($found) {
                    $matches->push($tenant);
                }
            } catch (\Throwable $th) {
                // One unreachable or half provisioned tenant database must not
                // hide the hospitals the others can answer for.
                Log::warning('Could not search a tenant database for a patient record.', [
                    'tenant_id' => $tenant->id,
                    'database'  => $tenant->database,
                    'exception' => $th->getMessage(),
                ]);
            }
        }

        config(['database.connections.tenant.database' => $originalDatabase]);
        DB::purge('tenant');

        return $matches;
    }

    /**
     * Look up the invitation a hospital created for this patient.
     *
     * @return array{status:string, profile:?array, verification_token:?string, expires_at:?string}
     */
    public function verifyInvitation(string $identifier, string $hospitalUuid): array
    {
        $tenant = $this->resolveTenant($hospitalUuid);
        $identifier = trim($identifier);

        $patient = $this->findPatient($identifier, $tenant);

        if (!$patient) {
            return [
                'status'             => self::STATUS_NOT_FOUND,
                'profile'            => [
                    'identifier' => $identifier,
                    'hospital'   => $tenant->name,
                ],
                'verification_token' => null,
                'expires_at'         => null,
            ];
        }

        $user = $this->findUserForPatient($patient);

        // A patient record created before the app existed, or one whose account
        // creation failed, has no user yet. Treat it as an invitation and build
        // the account now so the patient is never stuck on this screen.
        if (!$user) {
            $user = $this->patientAccountService->provision($patient, $tenant)['user'];
        }

        $identifiedByEmail = $this->matchesEmail($identifier, $patient, $user);

        // An account that no longer owes us a password change is one the patient
        // has already set up, whether here or at another hospital they attend.
        if (!$user->must_change_password) {
            return [
                'status'             => self::STATUS_ACCOUNT_EXISTS,
                'profile'            => $this->invitationProfile($patient, $user, $tenant, $identifiedByEmail),
                'verification_token' => null,
                'expires_at'         => null,
            ];
        }

        // Reaching this screen means the patient produced an identifier only the
        // hospital and they know, so the address is considered confirmed.
        $this->markEmailVerified($user);

        $token = $this->issueVerificationToken($user, $patient, $tenant);

        return [
            'status'             => self::STATUS_INVITATION_FOUND,
            'profile'            => $this->invitationProfile($patient, $user, $tenant, $identifiedByEmail),
            'verification_token' => $token->token,
            'expires_at'         => $token->expires_at->toIso8601String(),
        ];
    }

    /**
     * Spend a verification token to set the patient's own password.
     *
     * @return array{user:array, accessToken:string, tokenType:string}
     */
    public function createPassword(string $token, string $password): array
    {
        $verification = PatientVerificationToken::usable()->where('token', $token)->first();

        if (!$verification) {
            throw new PatientAuthException('This verification link is invalid or has expired. Please verify your invitation again.', 400);
        }

        $user = User::on('landlord')->find($verification->user_id);
        $tenant = Tenant::find($verification->tenant_id);

        if (!$user || !$tenant) {
            throw new PatientAuthException('We could not find the account this verification belongs to.', 404);
        }

        DB::connection('landlord')->transaction(function () use ($user, $tenant, $password) {
            $user->forceFill([
                'password'             => Hash::make($password),
                'must_change_password' => 0,
                'can_login'            => 1,
                'is_verified'          => 1,
                'is_completed'         => 1,
                'status'               => GeneralEnums::ACTIVE->value,
                'email_verified_at'    => $user->email_verified_at ?? now(),
            ])->save();

            TenantUser::on('landlord')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->update([
                    'status'    => GeneralEnums::ACTIVE->value,
                    'is_active' => 1,
                ]);

            // One token, one password. Every other outstanding invitation for
            // this account is spent too, so an old mail cannot reset it later.
            PatientVerificationToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
        });

        $tenant->makeCurrent();
        $patient = $this->patientFor($user, $tenant);

        return [
            'user'        => $this->sessionProfile($user->fresh(), $tenant, $patient),
            'accessToken' => JWTAuth::fromUser($user),
            'tokenType'   => 'Bearer',
        ];
    }

    /**
     * Sign a patient in to one of the hospitals they belong to.
     *
     * @return array{user:array, accessToken:string, tokenType:string}
     */
    public function login(string $identifier, string $password, string $hospitalUuid): array
    {
        $tenant = $this->resolveTenant($hospitalUuid);
        $user = $this->findUserByIdentifier(trim($identifier));

        if (!$user) {
            throw new PatientAuthException('Invalid login details.', 400);
        }

        $tenant->makeCurrent();

        $tenantUser = TenantUser::on('landlord')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$tenantUser) {
            throw new PatientAuthException('You are not registered with this hospital.', 403);
        }

        if (!$this->holdsPatientRole($user, $tenant)) {
            throw new PatientAuthException('This account is not a patient account for the selected hospital.', 403);
        }

        if (!$token = JWTAuth::attempt(['email' => $user->email, 'password' => $password])) {
            throw new PatientAuthException('Invalid login details.', 400);
        }

        if (!$user->can_login) {
            throw new PatientAuthException('Your account has been deactivated. Please contact the hospital.', 403);
        }

        if ($tenantUser->status !== GeneralEnums::ACTIVE->value) {
            throw new PatientAuthException('Your account for this hospital is inactive.', 403);
        }

        $user->forceFill(['last_login' => now()])->save();

        $patient = $this->patientFor($user, $tenant);

        return [
            'user'        => $this->sessionProfile($user, $tenant, $patient, $tenantUser),
            'accessToken' => $token,
            'tokenType'   => 'Bearer',
        ];
    }

    /**
     * Start a password reset by mailing the patient a one time code.
     *
     * The code goes to the address on the account, never to whatever was typed,
     * so entering a phone number cannot redirect a reset anywhere.
     *
     * @return array{email:string, expires_at:string, expires_in_minutes:int}
     */
    public function requestPasswordResetOtp(string $identifier): array
    {
        $user = $this->findUserByIdentifier(trim($identifier));

        if (!$user) {
            throw new PatientAuthException('We could not find an account with those details.', 404);
        }

        if (!$user->can_login) {
            throw new PatientAuthException('Your account has been deactivated. Please contact your hospital.', 403);
        }

        $ttl = (int) config('patient_app.password_reset_otp_ttl');
        $otp = random_int(100000, 999999);
        $expiresAt = now()->addMinutes($ttl);

        // password_reset_tokens is keyed by email, so a fresh request replaces
        // the previous code rather than leaving two of them live at once.
        DB::connection('landlord')->table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'user_id'     => $user->id,
                'otp'         => $otp,
                'token'       => Str::random(64),
                'status'      => 'pending',
                'verified_at' => null,
                'created_at'  => now(),
                'expires_at'  => $expiresAt,
            ]
        );

        try {
            Mail::to($user->email)->send(new PatientPasswordResetOtpMail([
                'name'               => $user->fullname ?: trim("{$user->first_name} {$user->last_name}"),
                'email'              => $user->email,
                'otp'                => $otp,
                'expires_in_minutes' => $ttl,
            ]));
        } catch (\Throwable $th) {
            Log::error('Failed to send the patient password reset code.', [
                'email'     => $user->email,
                'exception' => $th->getMessage(),
            ]);

            throw new PatientAuthException('We could not send your reset code. Please try again shortly.', 500);
        }

        return [
            // Masked: the caller may have arrived here with only a phone number,
            // and this response should not turn one into the other.
            'email'              => $this->maskEmail($user->email),
            'expires_at'         => $expiresAt->toIso8601String(),
            'expires_in_minutes' => $ttl,
        ];
    }

    /**
     * Check the code and hand back the token the reset screen spends.
     *
     * @return array{reset_token:string, expires_at:string}
     */
    public function verifyPasswordResetOtp(string $identifier, string $otp): array
    {
        $user = $this->findUserByIdentifier(trim($identifier));

        if (!$user) {
            throw new PatientAuthException('We could not find an account with those details.', 404);
        }

        $record = DB::connection('landlord')->table('password_reset_tokens')
            ->where('email', $user->email)
            ->where('otp', $otp)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            throw new PatientAuthException('This code is incorrect or has expired. Please request a new one.', 400);
        }

        // The clock restarts here: the code proved ownership, and what it buys
        // is a fresh window to type the new password in.
        $expiresAt = now()->addMinutes((int) config('patient_app.password_reset_token_ttl'));

        DB::connection('landlord')->table('password_reset_tokens')
            ->where('email', $user->email)
            ->update([
                'status'      => 'Verified',
                'verified_at' => now(),
                // Spent, so it cannot be replayed against this row.
                'otp'         => null,
                'expires_at'  => $expiresAt,
            ]);

        return [
            'reset_token' => $record->token,
            'expires_at'  => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Set the new password against a verified reset token.
     */
    public function resetPasswordWithToken(string $token, string $password): void
    {
        $record = DB::connection('landlord')->table('password_reset_tokens')
            ->where('token', $token)
            ->where('status', 'Verified')
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            throw new PatientAuthException('This reset session is invalid or has expired. Please start again.', 400);
        }

        $user = User::on('landlord')->find($record->user_id);

        if (!$user) {
            throw new PatientAuthException('We could not find the account this reset belongs to.', 404);
        }

        DB::connection('landlord')->transaction(function () use ($user, $password, $record) {
            $user->forceFill([
                'password' => Hash::make($password),
                // They have chosen their own password now, so the temporary
                // password prompt and any pending invitation are both settled.
                'must_change_password' => 0,
                'can_login'            => 1,
                'is_verified'          => 1,
                'email_verified_at'    => $user->email_verified_at ?? now(),
            ])->save();

            PatientVerificationToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            DB::connection('landlord')->table('password_reset_tokens')
                ->where('email', $record->email)
                ->delete();
        });
    }

    /**
     * Remember the face ID / fingerprint choice made after the first sign in.
     */
    public function setBiometric(User $user, bool $enabled): User
    {
        $user->forceFill(['biometric_enabled' => $enabled])->save();

        return $user;
    }

    /**
     * Replace the password of a signed in patient, clearing the change prompt
     * the mailed temporary password left behind.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new PatientAuthException('Your current password is incorrect.', 400);
        }

        $user->forceFill([
            'password'             => Hash::make($newPassword),
            'must_change_password' => 0,
        ])->save();

        return $user;
    }

    /**
     * The hospitals a signed in patient attends, for the hospital switcher.
     */
    public function hospitalsForUser(User $user)
    {
        return $user->tenants()
            ->where('tenants.status', GeneralEnums::ACTIVE->value)
            ->get(['tenants.id', 'tenants.uuid', 'tenants.name', 'tenants.logo', 'tenants.address']);
    }

    /**
     * Resolve and activate the hospital the request is about.
     */
    protected function resolveTenant(string $hospitalUuid): Tenant
    {
        $tenant = Tenant::where('uuid', $hospitalUuid)->first();

        if (!$tenant) {
            throw new PatientAuthException('Invalid hospital selected.', 404);
        }

        if ($tenant->status !== GeneralEnums::ACTIVE->value) {
            throw new PatientAuthException('This hospital is not currently active.', 403);
        }

        $tenant->makeCurrent();

        return $tenant;
    }

    /**
     * Find the patient a hospital registered under this email or phone number.
     */
    protected function findPatient(string $identifier, Tenant $tenant): ?Patient
    {
        return Patient::query()
            ->where('tenant_id', $tenant->uuid)
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('phoneno', $identifier);
            })
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * The account behind a patient record: the linked one, or the account that
     * already owns the same email for records created before the link existed.
     */
    protected function findUserForPatient(Patient $patient): ?User
    {
        if ($patient->user_id) {
            $user = User::on('landlord')->find($patient->user_id);

            if ($user) {
                return $user;
            }
        }

        if (empty($patient->email)) {
            return null;
        }

        return User::on('landlord')->where('email', trim(strtolower($patient->email)))->first();
    }

    /**
     * Find an account by either of the identifiers the login screen accepts.
     */
    protected function findUserByIdentifier(string $identifier): ?User
    {
        return User::on('landlord')
            ->where(function ($query) use ($identifier) {
                $query->where('email', strtolower($identifier))
                    ->orWhere('phone_number', $identifier);
            })
            ->first();
    }

    /**
     * The patient record this account holds at the given hospital.
     */
    protected function patientFor(User $user, Tenant $tenant): ?Patient
    {
        return Patient::query()
            ->where('tenant_id', $tenant->uuid)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->orderBy('id', 'DESC')
            ->first();
    }

    protected function holdsPatientRole(User $user, Tenant $tenant): bool
    {
        return $user->roles()
            ->where('roles.tenant_id', $tenant->uuid)
            ->where('roles.name', RoleEnums::PATIENT->value)
            ->exists();
    }

    protected function markEmailVerified(User $user): void
    {
        if ($user->email_verified_at && $user->is_verified) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_verified'       => 1,
        ])->save();
    }

    protected function issueVerificationToken(User $user, Patient $patient, Tenant $tenant): PatientVerificationToken
    {
        // Only the newest token may be spent, so anything still outstanding for
        // this account is retired first.
        PatientVerificationToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return PatientVerificationToken::create([
            'user_id'    => $user->id,
            'tenant_id'  => $tenant->id,
            'patient_no' => $patient->patientno,
            'token'      => Str::random(64),
            'purpose'    => 'invitation',
            'expires_at' => now()->addMinutes(config('patient_app.verification_token_ttl')),
        ]);
    }

    /**
     * What the "Invitation Found" / "Account Already Exist" screens show.
     *
     * The phone number and the hospital number are masked: this endpoint is
     * public, so it confirms what the caller already knows without handing out
     * anything new. The email is only shown in full when it is what they typed.
     */
    protected function invitationProfile(Patient $patient, User $user, Tenant $tenant, bool $identifiedByEmail): array
    {
        return [
            'patient_name'         => trim("{$patient->lastname}, {$patient->firstname}"),
            'gender'               => $patient->gender,
            'hospital'             => [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'logo' => $tenant->logo,
            ],
            'email'                => $identifiedByEmail ? $user->email : $this->maskEmail($user->email),
            'phone_number'         => $this->maskTail($patient->phoneno ?? $user->phone_number),
            'hospital_number'      => $this->maskTail($patient->patientno, 2),
            'hospital_number_hint' => $this->patientNumberHint($patient->patientno),
            'has_account'          => !$user->must_change_password,
        ];
    }

    /**
     * The payload behind a signed in session, for the app's home screen.
     */
    protected function sessionProfile(User $user, Tenant $tenant, ?Patient $patient, ?TenantUser $tenantUser = null): array
    {
        $tenantUser ??= TenantUser::on('landlord')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        $profile = $user->only([
            'id',
            'uuid',
            'fullname',
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'status',
            'profile_picture',
            'email_verified_at',
        ]);

        $profile['must_change_password'] = (bool) $user->must_change_password;
        $profile['biometric_enabled'] = (bool) $user->biometric_enabled;
        $profile['current_tenant'] = [
            'id'      => $tenant->id,
            'uuid'    => $tenant->uuid,
            'name'    => $tenant->name,
            'logo'    => $tenant->logo,
            'address' => $tenant->address,
        ];
        $profile['current_tenant_user'] = $tenantUser;
        $profile['patient'] = $patient ? [
            'id'         => $patient->id,
            'patientno'  => $patient->patientno,
            'cardno'     => $patient->cardno,
            'firstname'  => $patient->firstname,
            'lastname'   => $patient->lastname,
            'middlename' => $patient->middlename,
            'gender'     => $patient->gender,
            'dob'        => $patient->dob,
            'bloodgroup' => $patient->bloodgroup,
            'genotype'   => $patient->genotype,
            'status'     => $patient->status,
        ] : null;

        return $profile;
    }

    /**
     * A short, recognisable form of the hospital number for the "Patient ID"
     * line above the create password form.
     *
     * Patient numbers read EMED/60362/28312/NEH, so the tail alone comes out as
     * "/NEH" — the separator and the hospital acronym, telling the patient
     * nothing. Pairing the acronym with the last number instead gives
     * NEH-28312, which is what they can match against their card.
     */
    protected function patientNumberHint(?string $patientNo): ?string
    {
        if (empty($patientNo)) {
            return null;
        }

        $segments = array_values(array_filter(preg_split('/[^A-Za-z0-9]+/', $patientNo)));

        if (empty($segments)) {
            return null;
        }

        $acronym = end($segments);
        $numbers = array_values(array_filter($segments, 'ctype_digit'));

        return empty($numbers) ? $acronym : $acronym . '-' . end($numbers);
    }

    protected function matchesEmail(string $identifier, Patient $patient, User $user): bool
    {
        $identifier = strtolower($identifier);

        return $identifier === strtolower((string) $user->email)
            || $identifier === strtolower((string) $patient->email);
    }

    /**
     * Keep the last few characters of a value and hide the rest.
     */
    protected function maskTail(?string $value, int $visible = 2): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (strlen($value) <= $visible + 2) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 2) . str_repeat('*', strlen($value) - ($visible + 2)) . substr($value, -$visible);
    }

    protected function maskEmail(?string $email): ?string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);

        $visible = substr($name, 0, 2);

        return $visible . str_repeat('*', max(strlen($name) - 2, 1)) . '@' . $domain;
    }
}
