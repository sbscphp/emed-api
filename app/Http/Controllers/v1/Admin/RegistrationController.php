<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\TenantOnboardingRequest;
use App\Mail\TenantEmailVerification;
use App\Models\Registration;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegistrationController extends Controller
{
    protected RegistrationService $registrationService;

    public function __construct(RegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }
    //create an automatic instance of tenant database
    // public function onboardTenant(TenantOnboardingRequest $request)
    // {
    //     try {
    //         DB::connection('landlord')->beginTransaction();

    //         $data = $request->validated();
    //         $adminRole = Role::where('name', 'admin')->first();
    //         $registrationData = [
    //             'name' => $data['name'],
    //             'state_city' => $data['state_city'],
    //             'registration_number' => $data['registration_number'],
    //             'email' => $data['email'],
    //             'phone_number' => $data['phone_number'],
    //             'address' => $data['address'],
    //         ];

    //         if ($request->hasFile('license')) {
    //             $registrationData['license'] = FileUploadHelper::singleBinaryFileUpload(
    //                 $request->file('license'),
    //                 'License'
    //             );
    //         }

    //         $registration = Registration::create($registrationData);

    //         $existingTenant = Tenant::where('domain', Str::slug($data['name'], '-') . '.emed.com')->first();
    //         if ($existingTenant) {
    //             DB::connection('landlord')->rollBack();
    //             return JsonResponser::send(false, "Tenant {$data['name']} already exists.", [], 409);
    //         }

    //         $tenant = Tenant::create([
    //             'name' => $data['name'],
    //             'domain' => Str::slug($data['name'], '-') . '.emed.com',
    //             'database' => 'tenant_' . Str::slug($data['name'], '_') . '_' . Str::random(4),
    //         ]);
    //         DB::connection('landlord')->commit();

    //         try {
    //             DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    //             $tenant->makeCurrent();

    //             config(['database.connections.tenant.database' => $tenant->database]);
    //             DB::purge('tenant');
    //             DB::reconnect('tenant');

    //             Artisan::call('migrate', [
    //                 '--database' => 'tenant',
    //                 '--path' => 'database/migrations/tenant',
    //                 '--force' => true,
    //             ]);

    //             Artisan::call('db:seed', [
    //                 '--database' => 'tenant',
    //                 '--class' => 'RolePermissionSeeder',
    //                 '--force' => true,
    //             ]);

    //             Artisan::call('db:seed', [
    //                 '--database' => 'tenant',
    //                 '--class' => 'ServicesTableSeeder',
    //                 '--force' => true,
    //             ]);

    //             Artisan::call('db:seed', [
    //                 '--database' => 'tenant',
    //                 '--class' => 'StateSeeder',
    //                 '--force' => true,
    //             ]);
    //             DB::connection('tenant')->table('tenants')->insert([
    //                 'id' => $tenant->id,
    //                 'name' => $tenant->name,
    //                 'domain' => $tenant->domain,
    //                 'database' => $tenant->database,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);

    //             $adminLandlord = User::on('landlord')->create([
    //                 'uuid' => Str::uuid(),
    //                 'fullname' => $data['admin_fullname'],
    //                 'role' => $data['admin_role'],
    //                 'phone_number' => $data['admin_phone_number'],
    //                 'email' => $data['admin_email'],
    //                 'password' => $data['admin_password'],
    //                 'tenant_id' => $tenant->id,
    //                 'remember_token' => Str::random(60),
    //             ]);

    //             $adminData = [
    //                 'id' => $adminLandlord->id,
    //                 'uuid' => $adminLandlord->uuid,
    //                 'fullname' => $adminLandlord->fullname,
    //                 'role' => $adminLandlord->role,
    //                 'phone_number' => $adminLandlord->phone_number,
    //                 'email' => $adminLandlord->email,
    //                 'password' => $adminLandlord->password,
    //                 'tenant_id' => $tenant->id,
    //                 'remember_token' => $adminLandlord->remember_token,
    //             ];

    //             $adminTenantId  = DB::connection('tenant')->table('users')->insertGetId($adminData);
    //             $adminTenant  = User::on('tenant')->find($adminTenantId);


    //             $adminTenant->addRole($adminRole);
    //             $adminTenant->permissions()->sync($adminRole->permissions);
    //             $verificationCode = $adminTenant->remember_token;
    //             $verificationUrl = url('/verify-email/' . $verificationCode . '?email=' . urlencode($data['admin_email']));

    //             $adminTenant->remember_token = $verificationCode;
    //             $adminTenant->save();
    //             Mail::to($adminTenant->email)->send(new TenantEmailVerification($verificationUrl, [
    //                 'firstname' => $data['admin_fullname'],
    //                 'email' => $data['admin_email'],
    //                 'verification_code' => $verificationCode,
    //             ]));

    //             $dataToLog = [
    //                 'causer_id' => $adminTenant->id,
    //                 'action_id' => $adminTenant->id,
    //                 'action_type' => "App\Models\User",
    //                 'log_name' => "Tenant Created Successfully",
    //                 'description' => "{$adminTenant['fullname']} added successfully",
    //             ];
    //             GeneralHelper::storeAuditLog($dataToLog);

    //             return JsonResponser::send(
    //                 true,
    //                 'Tenant onboarding completed successfully. Please check your email to verify your account.',
    //                 [
    //                     'tenant' => $tenant,
    //                     'registration' => $registration,
    //                     'admin' => $adminTenant,
    //                 ],
    //                 200
    //             );
    //         } catch (\Exception $e) {
    //             DB::statement("DROP DATABASE IF EXISTS {$tenant->database}");
    //             return JsonResponser::send(
    //                 false,
    //                 'An error occurred during tenant database: ' . $e->getMessage(),
    //                 null,
    //                 500
    //             );
    //         }
    //     } catch (\Exception $e) {
    //         DB::connection('landlord')->rollBack();
    //         return JsonResponser::send(
    //             false,
    //             'An error occurred during tenant onboarding: ' . $e->getMessage(),
    //             null,
    //             500
    //         );
    //     }
    // }

    //manually create a tenant database
    // public function onboardTenant(TenantOnboardingRequest $request)
    // {
    //     try {
    //         DB::connection('landlord')->beginTransaction();

    //         $data = $request->validated();
    //         $adminRole = Role::where('name', 'admin')->first();
    //         $registrationData = [
    //             'name' => $data['name'],
    //             'state_city' => $data['state_city'],
    //             'registration_number' => $data['registration_number'],
    //             'email' => $data['email'],
    //             'phone_number' => $data['phone_number'],
    //             'address' => $data['address'],
    //         ];

    //         if ($request->hasFile('license')) {
    //             $registrationData['license'] = FileUploadHelper::singleBinaryFileUpload(
    //                 $request->file('license'),
    //                 'License'
    //             );
    //         }

    //         $registration = Registration::create($registrationData);

    //         $existingTenant = Tenant::where('domain', Str::slug($data['name'], '-') . '.emed.com')->first();
    //         if ($existingTenant) {
    //             DB::connection('landlord')->rollBack();
    //             return JsonResponser::send(false, "Tenant {$data['name']} already exists.", [], 409);
    //         }

    //         $tenant = Tenant::create([
    //             'name' => $data['name'],
    //             'domain' => Str::slug($data['name'], '-') . '.emed.com',
    //             'database' => 'jkpmjemy_emed_' . Str::slug($data['name'], '') . '' . Str::random(4),
    //         ]);
    //         DB::connection('landlord')->commit();

    //         try {
    //             // Check if the database exists (since we cannot create it)
    //             $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenant->database]);

    //             if (!$dbExists) {
    //                 return JsonResponser::send(
    //                     false,
    //                     "Database {$tenant->database} does not exist. Please create it manually in cPanel before onboarding this tenant.",
    //                     null,
    //                     500
    //                 );
    //             }

    //             $tenant->makeCurrent();
    //             config(['database.connections.tenant.database' => $tenant->database]);
    //             DB::purge('tenant');
    //             DB::reconnect('tenant');

    //             Artisan::call('migrate', [
    //                 '--database' => 'tenant',
    //                 '--path' => 'database/migrations/tenant',
    //                 '--force' => true,
    //             ]);

    //             // Seed the roles table
    //             Artisan::call('db:seed', [
    //                 '--database' => 'tenant',
    //                 '--class' => 'RolePermissionSeeder',
    //                 '--force' => true,
    //             ]);

    //             Artisan::call('db:seed', [
    //                 '--database' => 'tenant',
    //                 '--class' => 'ServicesTableSeeder',
    //                 '--force' => true,
    //             ]);

    //             Artisan::call('db:seed', [
    //                 '--database' => 'tenant',
    //                 '--class' => 'StateSeeder',
    //                 '--force' => true,
    //             ]);
    //             DB::connection('tenant')->table('tenants')->insert([
    //                 'id' => $tenant->id,
    //                 'name' => $tenant->name,
    //                 'domain' => $tenant->domain,
    //                 'database' => $tenant->database,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);

    //             $adminLandlord = User::on('landlord')->create([
    //                 'uuid' => Str::uuid(),
    //                 'fullname' => $data['admin_fullname'],
    //                 'role' => $data['admin_role'],
    //                 'phone_number' => $data['admin_phone_number'],
    //                 'email' => $data['admin_email'],
    //                 'password' => $data['admin_password'],
    //                 'tenant_id' => $tenant->id,
    //                 'remember_token' => Str::random(60),
    //             ]);

    //             $adminData = [
    //                 'id' => $adminLandlord->id,
    //                 'uuid' => $adminLandlord->uuid,
    //                 'fullname' => $adminLandlord->fullname,
    //                 'role' => $adminLandlord->role,
    //                 'phone_number' => $adminLandlord->phone_number,
    //                 'email' => $adminLandlord->email,
    //                 'password' => $adminLandlord->password,
    //                 'tenant_id' => $tenant->id,
    //                 'remember_token' => $adminLandlord->remember_token,
    //             ];

    //             $adminTenantId  = DB::connection('tenant')->table('users')->insertGetId($adminData);
    //             $adminTenant  = User::on('tenant')->find($adminTenantId);


    //             $adminTenant->addRole($adminRole);
    //             $adminTenant->permissions()->sync($adminRole->permissions);
    //             $verificationCode = $adminTenant->remember_token;
    //             $verificationUrl = url('/verify-email/' . $verificationCode . '?email=' . urlencode($data['admin_email']));

    //             $adminTenant->remember_token = $verificationCode;
    //             $adminTenant->save();
    //             Mail::to($adminTenant->email)->send(new TenantEmailVerification($verificationUrl, [
    //                 'firstname' => $data['admin_fullname'],
    //                 'email' => $data['admin_email'],
    //                 'verification_code' => $verificationCode,
    //             ]));

    //             $dataToLog = [
    //                 'causer_id' => $adminTenant->id,
    //                 'action_id' => $adminTenant->id,
    //                 'action_type' => "App\Models\User",
    //                 'log_name' => "Tenant Created Successfully",
    //                 'description' => "{$adminTenant['fullname']} added successfully",
    //             ];
    //             GeneralHelper::storeAuditLog($dataToLog);

    //             return JsonResponser::send(
    //                 true,
    //                 'Tenant onboarding completed successfully. Please check your email to verify your account.',
    //                 [
    //                     'tenant' => $tenant,
    //                     'registration' => $registration,
    //                     'admin' => $adminTenant,
    //                 ],
    //                 200
    //             );
    //         } catch (\Exception $e) {
    //             DB::statement("DROP DATABASE IF EXISTS {$tenant->database}");
    //             return JsonResponser::send(
    //                 false,
    //                 'An error occurred during tenant database: ' . $e->getMessage(),
    //                 null,
    //                 500
    //             );
    //         }
    //     } catch (\Exception $e) {
    //         DB::connection('landlord')->rollBack();
    //         return JsonResponser::send(
    //             false,
    //             'An error occurred during tenant onboarding: ' . $e->getMessage(),
    //             null,
    //             500
    //         );
    //     }
    // }
    public function onboardTenant(TenantOnboardingRequest $request)
    {
        try {
            DB::connection('landlord')->beginTransaction();

            $data = $request->validated();
            $adminRole = Role::where('name', 'admin')->first();
            $registrationData = [
                'name' => $data['name'],
                'state_city' => $data['state_city'],
                'registration_number' => $data['registration_number'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
                'address' => $data['address'],
            ];

            if ($request->hasFile('license')) {
                $registrationData['license'] = FileUploadHelper::singleBinaryFileUpload(
                    $request->file('license'),
                    'License'
                );
            }

            $registration = Registration::create($registrationData);

            $existingTenant = Tenant::where('domain', Str::slug($data['name'], '-') . '.emed.com')->first();
            if ($existingTenant) {
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(false, "Tenant {$data['name']} already exists.", [], 409);
            }

            $tenant = Tenant::create([
                'name' => $data['name'],
                'domain' => Str::slug($data['name'], '-') . '.emed.com',
                'database' => 'jkpmjemy_emed_' . Str::slug($data['name'], '') . '' . Str::random(4),
            ]);
            DB::connection('landlord')->commit();

            try {
                // Check if the database exists (since we cannot create it)
                $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenant->database]);

                if (!$dbExists) {
                    return JsonResponser::send(
                        false,
                        "Database {$tenant->database} does not exist. Please create it manually in cPanel before onboarding this tenant.",
                        null,
                        500
                    );
                }

                $tenant->makeCurrent();
                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);

                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'ServicesTableSeeder',
                    '--force' => true,
                ]);

                $adminData = [
                    'uuid' => Str::uuid(),
                    'fullname' => $data['admin_fullname'],
                    'role' => $data['admin_role'],
                    'phone_number' => $data['admin_phone_number'],
                    'email' => $data['admin_email'],
                    'password' => $data['admin_password'],
                    'remember_token' => Str::random(60),
                    'tenant_id' => $tenant->id,
                ];

                $admin = $this->registrationService->saveAdminDetails($adminData, $tenant->id);
                $admin->addRole($adminRole);
                $admin->permissions()->sync($adminRole->permissions);
                $verificationCode = Str::random(40);
                $verificationUrl = url('/verify-email/' . $verificationCode . '?email=' . urlencode($data['admin_email']));

                $admin->remember_token = $verificationCode;
                $admin->save();
                Mail::to($admin->email)->send(new TenantEmailVerification($verificationUrl, [
                    'firstname' => $data['admin_fullname'],
                    'email' => $data['admin_email'],
                    'verification_code' => $verificationCode,
                ]));

                $dataToLog = [
                    'causer_id' => $admin->id,
                    'action_id' => $admin->id,
                    'action_type' => "App\Models\User",
                    'log_name' => "Tenant Created Successfully",
                    'description' => "{$admin['fullname']} added successfully",
                ];
                GeneralHelper::storeAuditLog($dataToLog);

                return JsonResponser::send(
                    true,
                    'Tenant onboarding completed successfully. Please check your email to verify your account.',
                    [
                        'tenant' => $tenant,
                        'registration' => $registration,
                        'admin' => $admin,
                    ],
                    200
                );
            } catch (\Exception $e) {
                return JsonResponser::send(
                    false,
                    'An error occurred during tenant database setup. Please contact support.',
                    null,
                    500
                );
            }
        } catch (\Exception $e) {
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(
                false,
                'An error occurred during tenant onboarding: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    //LOGIN::THROUGH LANDLORD DB
    // public function adminLogin(AdminLoginRequest $request)
    // {
    //     try {
    //         $credentials = $request->only('email', 'password');

    //         $user = User::where('email', $credentials['email'])->first();

    //         if (!$user) {
    //             return JsonResponser::send(false, 'Invalid credentials', [], 401);
    //         }

    //         if (!$user->is_verified) {
    //             return JsonResponser::send(false, 'Your email has not been verified. Please check your email for verification.', [], 403);
    //         }

    //         if (!$token = JWTAuth::attempt($credentials)) {
    //             return JsonResponser::send(false, 'Invalid credentials', [], 401);
    //         }

    //         $tenant = Tenant::find($user->tenant_id);

    //         if (!$tenant) {
    //             JWTAuth::setToken($token)->invalidate();
    //             return JsonResponser::send(false, 'Tenant not found for this user', [], 404);
    //         }

    //         $hospital = User::where('tenant_id', $tenant->id)->first();
    //         if (!$hospital) {
    //             JWTAuth::setToken($token)->invalidate();
    //             return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
    //         }

    //         // Updating user fields when logging in for the first time after verification
    //         if (!$user->email_verified_at) {
    //             $user->update([
    //                 'email_verified_at' => now(),
    //                 'status' => 'active',
    //                 'is_verified' => true,
    //                 'can_login' => true,
    //                 'is_active' => true,
    //             ]);
    //         }

    //         return JsonResponser::send(
    //             true,
    //             'Admin logged in successfully',
    //             [
    //                 'user' => $user,
    //                 'tenant' => $tenant,
    //                 'hospital_registration' => $hospital,
    //                 'token' => $token,
    //             ],
    //             200
    //         );
    //     } catch (\Exception $e) {
    //         return JsonResponser::send(
    //             false,
    //             'An error occurred during Login. ' . $e->getMessage(),
    //             null,
    //             500
    //         );
    //     }
    // }

    //LOGIN THROUGH INDIVIDUAL TENANT DB 
    public function adminLogin(AdminLoginRequest $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            $tenantDomain = $request->input('tenant_domain');
            if (!$tenantDomain) {
                return JsonResponser::send(false, 'Tenant domain is required.', [], 400);
            }

            $tenant = Tenant::where('domain', $tenantDomain)->first();
            if (!$tenant) {
                return JsonResponser::send(false, 'Tenant not found.', [], 404);
            }


            $tenant->makeCurrent();
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $user = User::on('tenant')->where('email', $credentials['email'])->first();
            if (!$user) {
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            if (!$user->is_verified) {
                return JsonResponser::send(false, 'Your email has not been verified. Please check your email for verification.', [], 403);
            }

            if (!$token = JWTAuth::attempt($credentials)) {
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            $hospital = User::on('tenant')->where('tenant_id', $tenant->id)->first();
            if (!$hospital) {
                JWTAuth::setToken($token)->invalidate();
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
            }

            if (!$user->email_verified_at) {
                $user->update([
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'is_verified' => true,
                    'can_login' => true,
                    'is_active' => true,
                ]);
            }

            return JsonResponser::send(
                true,
                'Admin logged in successfully',
                [
                    'user' => $user,
                    'tenant' => $tenant,
                    'hospital_registration' => $hospital,
                    'token' => $token,
                ],
                200
            );
        } catch (\Exception $e) {
            return JsonResponser::send(
                false,
                'An error occurred during Login. ' . $e->getMessage(),
                null,
                500
            );
        }
    }


    public function verifyEmail($token)
    {
        try {
            DB::beginTransaction();

            $landlordUser = User::on('landlord')->where('remember_token', $token)->first();

            if (!$landlordUser) {
                return JsonResponser::send(
                    false,
                    'Invalid or expired verification link.',
                    null,
                    404
                );
            }

            $tenant = DB::connection('landlord')->table('tenants')
                ->where('id', $landlordUser->tenant_id)
                ->first();

            if (!$tenant) {
                return JsonResponser::send(
                    false,
                    'Tenant not found in landlord database.',
                    null,
                    404
                );
            }

            config(['database.connections.tenant.database' => $tenant->database]);

            DB::purge('tenant');
            DB::reconnect('tenant');

            $tenantUser = User::on('tenant')->where('email', $landlordUser->email)->first();

            if (!$tenantUser) {
                return JsonResponser::send(
                    false,
                    'User not found in tenant database.',
                    null,
                    404
                );
            }

            $updateData = [
                'is_verified' => true,
                'email_verified_at' => now(),
                'status' => 'active',
                'can_login' => true,
                'is_active' => true,
                'remember_token' => null,
            ];

            $landlordUser->update($updateData);
            $tenantUser->update($updateData);

            DB::commit();

            return JsonResponser::send(
                true,
                'Your email has been verified. You can now log in.',
                [
                    'user' => [
                        'email' => $landlordUser->email,
                    ]
                ],
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return JsonResponser::send(
                false,
                'An error occurred while verifying your email. Please try again later. ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
