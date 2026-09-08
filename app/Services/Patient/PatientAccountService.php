<?php

namespace App\Services\Patient;

use App\Enums\GeneralEnums;
use App\Enums\RoleEnums;
use App\Mail\PatientInvitationMail;
use App\Models\Patient;
use App\Models\PatientVerificationToken;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Class PatientAccountService
 *
 * Gives a patient record the landlord user account it signs into the patient
 * mobile app with, and mails the invitation that carries the credentials.
 *
 * A patient can be registered by more than one hospital, and the landlord users
 * table is keyed by a unique email, so the account is shared: the second
 * hospital reuses the existing user and only adds its own tenant membership and
 * patient role. That is also why the invitation mail drops the password half
 * when the account was not created by this registration — the patient already
 * has one, and we never have their plain text password to resend.
 */
class PatientAccountService
{
    public function __construct(protected UserService $userService) {}

    /**
     * Create (or reuse) the user account behind a patient record.
     *
     * Only touches the databases — the invitation mail is sent separately by
     * sendInvitation() so the caller can wait until its transactions committed.
     *
     * @return array{user: User, password: ?string, is_new: bool}
     */
    public function provision(Patient $patient, Tenant $tenant): array
    {
        $email = $patient->email ? trim(strtolower($patient->email)) : null;

        if (empty($email)) {
            throw new \RuntimeException('A patient needs an email address before an app account can be created.');
        }

        $user = User::on('landlord')->where('email', $email)->first();
        $password = null;
        $isNew = false;

        if (!$user) {
            $password = $this->userService->generateTemporaryPassword();

            $user = User::on('landlord')->create([
                'uuid'                 => (string) Str::uuid(),
                'fullname'             => trim("{$patient->firstname} {$patient->lastname}"),
                'first_name'           => $patient->firstname,
                'last_name'            => $patient->lastname,
                'email'                => $email,
                // phone_number is globally unique, and a household can share a
                // number, so it is only claimed when nobody holds it yet.
                'phone_number'         => $this->availablePhoneNumber($patient->phoneno),
                'password'             => bcrypt($password),
                'status'               => GeneralEnums::ACTIVE->value,
                'can_login'            => 1,
                'is_verified'          => 1,
                'is_completed'         => 1,
                // The patient is expected to replace the mailed password, both
                // from the app's "create password" screen and on first login.
                'must_change_password' => 1,
            ]);

            $isNew = true;
        }

        $this->attachPatientRole($user, $tenant);
        $this->attachToTenant($user, $patient, $tenant);

        // Tie the tenant side record back to the account it belongs to.
        if ($patient->user_id !== $user->id) {
            $patient->forceFill(['user_id' => $user->id])->save();
        }

        return [
            'user'     => $user,
            'password' => $password,
            'is_new'   => $isNew,
        ];
    }

    /**
     * Issue a fresh temporary password and mail the invitation again.
     *
     * The verification token from verify-invitation expires, and re-verifying
     * mints a new one — but only while the patient still has the temporary
     * password we mailed. A patient who lost that mail has no way back in, and
     * neither has one whose email never arrived. This is the records desk's
     * answer to both.
     *
     * For a patient who has already chosen their own password this is a
     * credential reset, not a re-invite, so it is refused unless the caller
     * says so explicitly. A clerk should not be able to silently overwrite a
     * password the patient picked — the app's own reset flow exists for that.
     *
     * @return array{user: User, password: string}
     */
    public function resendInvitation(Patient $patient, Tenant $tenant, bool $resetExistingPassword = false): array
    {
        $account = $this->provision($patient, $tenant);
        $user = $account['user'];

        // provision() only mints a password for an account it just created.
        if (!$account['is_new'] && !$user->must_change_password && !$resetExistingPassword) {
            throw new \RuntimeException(
                'This patient has already set their own password. Ask them to use "Forgot Password" in the app, or resend with reset_password enabled.'
            );
        }

        $password = $account['password'] ?? $this->userService->generateTemporaryPassword();

        $user->forceFill([
            'password'             => bcrypt($password),
            'must_change_password' => 1,
            'can_login'            => 1,
            'is_verified'          => 1,
        ])->save();

        // Any invitation still outstanding described the old password, so it
        // must not stay spendable alongside the one going out now.
        PatientVerificationToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return [
            'user'     => $user,
            'password' => $password,
        ];
    }

    /**
     * Mail the patient their welcome note, credentials and the store links.
     *
     * A mail failure must never undo a patient registration that already
     * succeeded, so it is logged and swallowed. The invitation can be resent
     * from the records desk afterwards.
     */
    public function sendInvitation(User $user, Patient $patient, Tenant $tenant, ?string $password = null): bool
    {
        try {
            Mail::to($user->email)->send(new PatientInvitationMail([
                'name'          => $user->fullname ?: trim("{$patient->firstname} {$patient->lastname}"),
                'email'         => $user->email,
                'hospital_name' => $tenant->name,
                'password'      => $password,
                'patient_no'    => $patient->patientno,
            ]));

            return true;
        } catch (\Throwable $th) {
            Log::error('Failed to send the patient invitation mail.', [
                'email'      => $user->email,
                'patient_id' => $patient->id,
                'tenant_id'  => $tenant->id,
                'exception'  => $th->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * The tenant's patient role, created on demand.
     *
     * RolePermissionSeeder seeds it for every new hospital, but hospitals
     * onboarded before the patient app existed have never run that seeder
     * again, so it cannot be assumed to be there.
     */
    public function patientRole(Tenant $tenant): Role
    {
        return Role::firstOrCreate(
            [
                'tenant_id' => $tenant->uuid,
                'name'      => RoleEnums::PATIENT->value,
            ],
            [
                'display_name' => 'Patient',
                'description'  => 'This role is held by patients of the hospital. It grants access to the patient mobile app only and no hospital console module.',
                'status'       => GeneralEnums::ACTIVE->value,
            ]
        );
    }

    /**
     * Attach the patient role for this tenant, leaving every other tenant's
     * roles alone — the same account may be staff at another hospital.
     *
     * The patient role carries no permissions on purpose, so nothing is copied
     * into permission_user here.
     */
    protected function attachPatientRole(User $user, Tenant $tenant): void
    {
        $role = $this->patientRole($tenant);

        if (!$user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->attach($role->id);
        }
    }

    /**
     * Record the patient's membership of this hospital.
     */
    protected function attachToTenant(User $user, Patient $patient, Tenant $tenant): TenantUser
    {
        return TenantUser::on('landlord')->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id'   => $user->id,
            ],
            [
                'display_name'  => trim("{$patient->firstname} {$patient->lastname}"),
                'date_of_birth' => $patient->dob,
                'status'        => GeneralEnums::ACTIVE->value,
                'is_active'     => 1,
            ]
        );
    }

    /**
     * Return the phone number only when no other account already holds it.
     */
    protected function availablePhoneNumber(?string $phoneNumber): ?string
    {
        if (empty($phoneNumber)) {
            return null;
        }

        $taken = User::on('landlord')->withTrashed()->where('phone_number', $phoneNumber)->exists();

        return $taken ? null : $phoneNumber;
    }
}
