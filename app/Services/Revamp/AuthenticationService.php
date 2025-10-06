<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
     * Retrieve all Laboratory.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function create($data)
    {
        $tenant = null;

        try {
            // Create Tenant
            $tenant = $this->createTenant($data);
            $tenant->makeCurrent(); // Switch DB to tenant

            // Create User (Hospital Admin)
            $user = $this->createUser($tenant, $data);

            $verificationCode = $user->remember_token;
            $baseUrl = env('APP_URL') . '/api/v1/admin/verify/email';
            $verificationUrl = $baseUrl . '?token=' . $verificationCode . '&email=' . urlencode($data['admin_email']);
            $user->save();

            Mail::to($user->email)->send(new TenantEmailVerification($verificationUrl, [
                'firstname' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'verification_code' => $verificationCode,
            ]));

            return [
                'user' => $user->load('roles', 'permissions'),
                'tenant' => $tenant
            ];
        } catch (\Throwable $e) {
            if (env('APP_ENV') === 'local') {
                if ($tenant && $tenant->database) {
                    DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$tenant->database}`");
                }
                if ($tenant) {
                    $tenant->delete();
                }
            }
            throw $e;
        }
    }

    /**
     * Create a tenant with domain, database, and UUID setup.
     *
     * @param array $data
     * @return Tenant
     * @throws \Exception
     */

    private function createTenant(array $data): Tenant
    {
        // Tenant DB and domain setup
        $slug = Str::slug($data['hospital_name']);
        $sanitizedSlug = str_replace('-', '_', $slug);

        if (isset($data['license']) && $data['license']) {
            $licenseFile = FileUploadHelper::singleBinaryFileUpload($data['license'], 'License');
        }

        $tenantData = [
            'uuid' => (string) Str::uuid(),
            'name' => $data['hospital_name'],
            'domain' => "{$slug}.emed.com",
            'database' => "tenant_{$sanitizedSlug}",
            'state_city' => $data['state_city'],
            'registration_number' => $data['registration_number'],
            'email' => $data['hospital_email'],
            'phone_number' => $data['hospital_phoneno'],
            'address' => $data['hospital_address'],
            'license' => $licenseFile ?? null,
        ];

        // Ensure domain and database are unique
        $existingTenant = Tenant::where('domain', $tenantData['domain'])->first();
        if ($existingTenant) {
            throw new \Exception("{$tenantData['name']} already exists.");
        }

        // Save tenant
        $tenant = Tenant::create($tenantData);

        // Create tenant DB in local/dev
        if (env('APP_ENV') === 'local') {
            DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database}");
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

            $tenant->forget();
        }

        return $tenant;
    }

    private function createUser($tenant, $data)
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
                'is_completed' => 1
            ]
        );

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
        if (! $user->roles->contains($role->id)) {
            $user->addRole($role);
        }

        // Assign all permissions
        $permissions = Permission::all();
        $role->givePermissions($permissions->pluck('id')->toArray());

        // Attach user to tenant with tenant-specific details
        TenantUser::firstOrCreate(
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

        return $user;
    }

    public function resendEmailVerification($email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw new \Exception("User with email {$email} not found.");
        }

        if ($user->is_verified) {
            throw new \Exception("Email already verified.");
        }

        $verificationCode = $user->remember_token;
        $baseUrl = env('APP_URL') . '/api/v1/admin/verify/email';
        $verificationUrl = $baseUrl . '?token=' . $verificationCode . '&email=' . urlencode($user->email);
        Mail::to($user->email)->send(new TenantEmailVerification($verificationUrl, [
            'firstname' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'verification_code' => $verificationCode,
        ]));

        return $user;
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
