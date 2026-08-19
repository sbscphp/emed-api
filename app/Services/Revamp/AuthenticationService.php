<?php

namespace App\Services\Revamp;

use App\Exceptions\RegistrationException;
use App\Helpers\FileUploadHelper;
use App\Mail\TenantEmailVerification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Repositories\Laboratory\LaboratoryInterface;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Class AuthenticationService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class AuthenticationService
{
    /**
     * Laboratory constructor.
     *
     */
    public function __construct(LaboratoryInterface $LaboratoryInterface) {}

    /**
     * Register a hospital (tenant) together with its first admin user.
     *
     * The flow spans the landlord tables, a freshly created tenant database and
     * the MySQL server itself, so a single transaction cannot cover it. Instead
     * every record we create is tracked and undone by rollbackRegistration()
     * when a later step fails.
     *
     * @return array{user: User, tenant: Tenant}
     */
    public function create($data)
    {
        $skipEmailVerification = (bool) ($data['skip_email_verification'] ?? false);

        // Everything we may have to undo when a later step blows up. It is
        // filled in by reference so a step that throws half way through still
        // reports what it already created.
        $created = [
            'tenant' => null,
            'database' => false,
            'user' => null,
            'tenantUser' => null,
        ];

        try {
            // Create Tenant on landlord DB
            $tenant = $this->createTenant($data, $created);

            // Create the tenant database, then migrate and seed it
            $this->provisionTenantDatabase($tenant, $created);

            // Switch DB to tenant
            $tenant->makeCurrent();

            // Create User (Hospital Admin) - roles/permissions live on the tenant DB
            $user = $this->createUser($tenant, $data, $created);

            if ($skipEmailVerification) {
                $user->update([
                    'email_verified_at' => now(),
                    'is_verified' => 1,
                    'can_login' => 1,
                    'otp' => null,
                ]);

                DB::connection($this->landlordConnection())
                    ->table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->delete();
            } else {
                $this->sendEmailVerificationOtp($user);
            }

            // Load tenant-scoped relations while tenant DB is still selected
            $userWithRelations = $user->load('roles', 'permissions');

            // Forget tenant connection after eager-loading tenant data
            $tenant->forget();
            $this->restoreDefaultConnection();

            return [
                'user' => $userWithRelations,
                'tenant' => $tenant
            ];
        } catch (\Throwable $e) {
            $this->rollbackRegistration($created);

            throw $e;
        }
    }

    /**
     * Create the tenant row on the landlord database.
     *
     * @param array $created rollback bookkeeping, filled in by reference
     * @throws RegistrationException
     */
    private function createTenant(array $data, array &$created): Tenant
    {
        // Tenant DB and domain setup
        $slug = Str::slug($data['hospital_name']);

        if ($slug === '') {
            throw new RegistrationException('Hospital name must contain at least one letter or number.');
        }

        $sanitizedSlug = str_replace('-', '_', $slug);

        $domain = "{$slug}.emed.com";
        $database = "tenant_{$sanitizedSlug}";

        // Ensure domain and database are unique. This has to look at soft
        // deleted tenants too: the row left behind by a deletion still holds
        // the unique indexes, so without it the insert below dies with a raw
        // "Duplicate entry ... for key 'tenants_domain_unique'" error.
        $this->guardAgainstExistingTenant($domain, $database, $data['hospital_name']);

        $licenseFile = null;
        if (!empty($data['license'])) {
            $licenseFile = FileUploadHelper::singleBinaryFileUpload($data['license'], 'License');
        }

        $tenantData = [
            'uuid' => (string) Str::uuid(),
            'name' => $data['hospital_name'],
            'country' => $data['country'] ?? null,
            'domain' => $domain,
            'database' => $database,
            'state_city' => $data['state_city'],
            'registration_number' => $data['registration_number'],
            'email' => $data['hospital_email'],
            'phone_number' => $data['hospital_phoneno'],
            'address' => $data['hospital_address'],
            'license' => $licenseFile,
        ];

        try {
            return $created['tenant'] = Tenant::create($tenantData);
        } catch (QueryException $e) {
            // Safety net for a concurrent registration slipping past the check above.
            if ($this->isDuplicateEntry($e)) {
                throw new RegistrationException("{$data['hospital_name']} already exists.", 0, $e);
            }

            throw $e;
        }
    }

    /**
     * Reject the registration when the domain or database name is taken.
     *
     * A soft deleted tenant whose database no longer exists can only be the
     * leftover of a registration that failed half way through, so it is purged
     * instead of blocking that hospital name forever.
     *
     * @throws RegistrationException
     */
    private function guardAgainstExistingTenant(string $domain, string $database, string $hospitalName): void
    {
        $existingTenants = Tenant::withTrashed()
            ->where(function ($query) use ($domain, $database) {
                $query->where('domain', $domain)->orWhere('database', $database);
            })
            ->get();

        foreach ($existingTenants as $existingTenant) {
            if ($existingTenant->trashed() && !$this->tenantDatabaseExists($existingTenant->database)) {
                Log::warning('Purging stale tenant record left behind by a failed registration.', [
                    'tenant_id' => $existingTenant->id,
                    'domain' => $existingTenant->domain,
                ]);

                $this->purgeTenantRecord($existingTenant);

                continue;
            }

            throw new RegistrationException("{$hospitalName} already exists.");
        }
    }

    /**
     * Create the tenant database and run its migrations and seeders.
     *
     * @param array $created rollback bookkeeping, filled in by reference
     */
    private function provisionTenantDatabase(Tenant $tenant, array &$created): void
    {
        // Never drop a database we did not create ourselves, so only flag it
        // for rollback when this call is the one that creates it.
        if (!$this->tenantDatabaseExists($tenant->database)) {
            DB::connection($this->landlordConnection())
                ->statement("CREATE DATABASE IF NOT EXISTS `{$tenant->database}`");

            $created['database'] = true;
        }

        $tenant->makeCurrent();

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true
        ]);

        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'DatabaseSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Create the hospital admin, its admin role and the tenant membership.
     *
     * @param array $created rollback bookkeeping, filled in by reference
     */
    private function createUser($tenant, $data, array &$created): User
    {
        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $data['admin_email']],
            [
                'uuid' => (string) Str::uuid(),
                'fullname' => $data['admin_fullname'] ?? NULL,
                'first_name' => $data['admin_firstname'] ?? NULL,
                'last_name' => $data['admin_lastname'] ?? NULL,
                'email' => $data['admin_email'] ?? NULL,
                'phone_number' => $data['admin_phoneno'] ?? NULL,
                'password' => bcrypt($data['admin_password']),
                'remember_token' => Str::random(60),
                'can_login' => 1,
                'is_verified' => $data['skip_email_verification'] ?? false,
                'email_verified_at' => ($data['skip_email_verification'] ?? false) ? now() : null,
                'is_completed' => 1
            ]
        );

        // Only a user this request created may be removed again on rollback:
        // an existing admin registering a second hospital must survive.
        if ($user->wasRecentlyCreated) {
            $created['user'] = $user;
        }

        // Create Admin role if not exists for this tenant
        $role = Role::firstOrCreate(
            ['tenant_id' => $tenant->uuid, 'name' => 'admin'],
            [
                'display_name' => 'Admin',
                'description' => 'Admin can access all modules and privileges.',
                'status' => 'Active'
            ]
        );

        // Attach role to user (idempotent)
        if (!$user->roles->contains($role->id)) {
            $user->addRole($role);
        }

        // Assign all permissions. RolePermissionSeeder already granted them
        // while the tenant database was seeded, so this has to be a sync:
        // givePermissions() attaches blindly and dies on a duplicate
        // permission_role primary key.
        $role->syncPermissions($permissions = Permission::pluck('id')->all());

        // Attach user to tenant with tenant-specific details
        $tenantUser = TenantUser::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id'   => $user->id,
            ],
            [
                'profile_picture' => $data['profile_picture'] ?? null,
                'display_name'    => $data['display_name'] ?? ($user->first_name . ' ' . $user->last_name),
                'date_of_birth'   => $data['date_of_birth'] ?? null,
                'status'          => 'Active',
                'is_active' => 1
            ]
        );

        if ($tenantUser->wasRecentlyCreated) {
            $created['tenantUser'] = $tenantUser;
        }

        return $user;
    }

    /**
     * Store the verification OTP and mail it to the new admin.
     *
     * A mail failure must not destroy an otherwise complete hospital, so it is
     * logged and the admin can request a fresh OTP from the resend endpoint.
     */
    private function sendEmailVerificationOtp(User $user): void
    {
        $otp = random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(30);
        $landlord = $this->landlordConnection();

        // Store token in landlord DB with separate transaction
        DB::connection($landlord)->transaction(function () use ($landlord, $user, $otp, $expiresAt) {
            DB::connection($landlord)->table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'otp' => $otp,
                    'created_at' => now(),
                    'expires_at' => $expiresAt
                ]
            );
        });

        $maildata = [
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'token' => $otp,
        ];

        try {
            Mail::to($user->email)->send(new TenantEmailVerification($maildata));
        } catch (\Throwable $e) {
            Log::error('Failed to send the registration verification mail.', [
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Undo everything a failed registration managed to create.
     *
     * Each step is isolated so a failing clean up step cannot hide the original
     * exception.
     */
    private function rollbackRegistration(array $created): void
    {
        $landlord = $this->landlordConnection();

        /** @var Tenant|null $tenant */
        $tenant = $created['tenant'];
        /** @var User|null $user */
        $user = $created['user'];
        /** @var TenantUser|null $tenantUser */
        $tenantUser = $created['tenantUser'];

        // Get off the (possibly half migrated) tenant connection first.
        $this->safely(function () {
            Tenant::forgetCurrent();
            $this->restoreDefaultConnection();
        }, 'reset the tenant connection');

        if ($tenantUser) {
            $this->safely(
                fn() => TenantUser::withTrashed()->whereKey($tenantUser->id)->forceDelete(),
                'delete the tenant user'
            );
        }

        if ($user) {
            // Separate steps: a failing token clean up must not stop the user
            // itself from being removed.
            $this->safely(
                fn() => User::withTrashed()->whereKey($user->id)->forceDelete(),
                'delete the user'
            );

            $this->safely(
                fn() => DB::connection($landlord)->table('password_reset_tokens')->where('email', $user->email)->delete(),
                'delete the password reset token'
            );
        }

        if ($tenant && $created['database'] && $tenant->database) {
            $this->safely(
                fn() => DB::connection($landlord)->statement("DROP DATABASE IF EXISTS `{$tenant->database}`"),
                'drop the tenant database'
            );
        }

        if ($tenant && $tenant->exists) {
            // Force delete: a soft deleted row keeps holding the unique domain
            // and database indexes, which blocks every later registration.
            $this->safely(fn() => $this->purgeTenantRecord($tenant), 'delete the tenant');
        }
    }

    /**
     * Permanently remove a tenant row and its landlord side memberships.
     */
    private function purgeTenantRecord(Tenant $tenant): void
    {
        TenantUser::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
        $tenant->forceDelete();
    }

    private function tenantDatabaseExists(?string $database): bool
    {
        if (empty($database)) {
            return false;
        }

        return DB::connection($this->landlordConnection())->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$database]
        ) !== null;
    }

    private function isDuplicateEntry(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    private function landlordConnection(): string
    {
        return config('multitenancy.landlord_database_connection_name', 'landlord');
    }

    /**
     * Making a tenant current leaves the default connection pointing at the
     * tenant connection, so put it back explicitly.
     */
    private function restoreDefaultConnection(): void
    {
        DB::setDefaultConnection(config('database.default'));
    }

    private function safely(callable $callback, string $description): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error("Registration rollback failed to {$description}.", [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function verifyEmail($token, $email)
    {
        $user = User::where('email', $email)->where('remember_token', $token)->first();
        if (!$user) {
            throw new \Exception("Invalid verification token or email.");
        }

        if ($user->email_verified_at) {
            throw new \Exception("Email already verified.");
        }

        $user->email_verified_at = Carbon::now();
        $user->remember_token = null;
        $user->is_verified = 1;
        $user->is_completed = 1;
        $user->save();

        return $user;
    }
}
