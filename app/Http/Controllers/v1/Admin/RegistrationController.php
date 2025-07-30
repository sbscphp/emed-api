<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\TenantOnboardingRequest;
use App\Http\Resources\UserResource;
use App\Mail\TenantEmailVerification;
use App\Models\Tenant;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Registration\RegistrationService;
use App\Services\Role\RoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends Controller
{
    protected RegistrationService $registrationService;
    protected RoleService $roleService;

    public function __construct(RegistrationService $registrationService, RoleService $roleService)
    {
        $this->registrationService = $registrationService;
        $this->roleService = $roleService;
    }

    //create a central tenant DB for all onboard tenant for testing on production or manually create on local
    // public function onboardTenant(TenantOnboardingRequest $request)
    // {
    //     // try {
    //     DB::connection('landlord')->beginTransaction();

    //     $data = $request->validated();
    //     $adminRole = $this->roleService->getAdminRole();

    //     $registrationData = [
    //         'name' => $data['name'],
    //         'state_city' => $data['state_city'],
    //         'registration_number' => $data['registration_number'],
    //         'email' => $data['email'],
    //         'phone_number' => $data['phone_number'],
    //         'address' => $data['address'],
    //     ];

    //     if ($request->hasFile('license')) {
    //         $registrationData['license'] = FileUploadHelper::singleBinaryFileUpload(
    //             $request->file('license'),
    //             'License'
    //         );
    //     }

    //     $registration = $this->registrationService->create($registrationData);

    //     $domain = Str::slug($data['name'], '-') . '.emed.com';
    //     $existingTenant = Tenant::where('domain',   $domain)->first();
    //     if ($existingTenant) {
    //         DB::connection('landlord')->rollBack();
    //         return JsonResponser::send(false, "Tenant {$data['name']} already exists.", [], 500);
    //     }

    //     $isProduction = app()->environment(['production', 'staging', 'qa']);
    //     $tenantDatabase = $isProduction
    //         // ? 'tenant_john_hospital'
    //         ? 'jkpmjemy_tenant_john_hospital'
    //         : 'tenant_' . Str::slug($data['name'], '_');

    //     // Create tenant

    //     $tenant = Tenant::create([
    //         'name' => $data['name'],
    //         'domain' => $domain,
    //         'database' => $tenantDatabase,
    //     ]);

    //     try {
    //         if (!$isProduction) {
    //             DB::statement("CREATE DATABASE IF NOT EXISTS {$tenantDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    //         } else {
    //             $dbExists = DB::connection('landlord')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenantDatabase]);
    //             if (empty($dbExists)) {
    //                 DB::connection('landlord')->rollBack();
    //                 return JsonResponser::send(
    //                     false,
    //                     "Database {$tenantDatabase} does not exist. Please create it manually before onboarding this tenant.",
    //                     null,
    //                     500
    //                 );
    //             }
    //         }

    //         $tenant->makeCurrent();

    //         config(['database.connections.tenant.database' => $tenantDatabase]);
    //         DB::purge('tenant');
    //         DB::reconnect('tenant');

    //         // Run migrations
    //         // Artisan::call('migrate', [
    //         //     '--database' => 'tenant',
    //         //     '--path' => 'database/migrations/tenant',
    //         //     '--force' => true,
    //         // ]);
    //         if (!Schema::connection('tenant')->hasTable('tenants')) {
    //             Artisan::call('migrate', [
    //                 '--database' => 'tenant',
    //                 '--path' => 'database/migrations/tenant',
    //                 '--force' => true,
    //             ]);
    //         }

    //         // Run seeders
    //         Artisan::call('db:seed', [
    //             '--database' => 'tenant',
    //             '--class' => 'RolePermissionSeeder',
    //             '--force' => true,
    //         ]);

    //         Artisan::call('db:seed', [
    //             '--database' => 'tenant',
    //             '--class' => 'ServicesTableSeeder',
    //             '--force' => true,
    //         ]);

    //         Artisan::call('db:seed', [
    //             '--database' => 'tenant',
    //             '--class' => 'StateSeeder',
    //             '--force' => true,
    //         ]);

    //         Artisan::call('db:seed', [
    //             '--database' => 'tenant',
    //             '--class' => 'ServiceUnitSeeder',
    //             '--force' => true,
    //         ]);

    //         Artisan::call('db:seed', [
    //             '--database' => 'tenant',
    //             '--class' => 'UsersTableSeeder',
    //             '--force' => true,
    //         ]);

    //         // Insert tenant metadata into tenant database
    //         DB::connection('tenant')->table('tenants')->insert([
    //             'id' => $tenant->id,
    //             'name' => $tenant->name,
    //             'domain' => $tenant->domain,
    //             'database' => $tenantDatabase,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         $adminLandlord = User::on('landlord')->create([
    //             'uuid' => Str::uuid(),
    //             'fullname' => $data['admin_fullname'],
    //             'role' => $data['admin_role'],
    //             'phone_number' => $data['admin_phone_number'],
    //             'email' => $data['admin_email'],
    //             'password' => $data['admin_password'],
    //             'tenant_id' => $tenant->id,
    //             'remember_token' => Str::random(60),
    //         ]);

    //         // $adminData = [
    //         //     'id' => $adminLandlord->id,
    //         //     'uuid' => $adminLandlord->uuid,
    //         //     'fullname' => $adminLandlord->fullname,
    //         //     'role' => $adminLandlord->role,
    //         //     'phone_number' => $adminLandlord->phone_number,
    //         //     'email' => $adminLandlord->email,
    //         //     'password' => $adminLandlord->password,
    //         //     'tenant_id' => $tenant->id,
    //         //     'remember_token' => $adminLandlord->remember_token,
    //         // ];
    //         $existingTenantUser = DB::connection('tenant')->table('users')->where('id', $adminLandlord->id)->first();

    //         if (!$existingTenantUser) {
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

    //             DB::connection('tenant')->table('users')->insert($adminData);
    //         }


    //         // $adminTenantId = DB::connection('tenant')->table('users')->insertGetId($adminData);
    //         // $adminTenant = User::on('tenant')->find($adminTenantId);
    //         $adminTenant = User::on('tenant')->find($adminLandlord->id);

    //         $adminTenant->addRole($adminRole);
    //         $adminTenant->permissions()->sync($adminRole->permissions);
    //         $verificationCode = $adminTenant->remember_token;
    //         $verificationUrl = url('/verify-email/' . $verificationCode . '?email=' . urlencode($data['admin_email']));

    //         $adminTenant->remember_token = $verificationCode;
    //         $adminTenant->save();

    //         Mail::to($adminTenant->email)->send(new TenantEmailVerification($verificationUrl, [
    //             'firstname' => $data['admin_fullname'],
    //             'email' => $data['admin_email'],
    //             'verification_code' => $verificationCode,
    //         ]));

    //         $dataToLog = [
    //             'causer_id' => $adminTenant->id,
    //             'action_id' => $adminTenant->id,
    //             'action_type' => "App\Models\User",
    //             'log_name' => "Tenant Created Successfully",
    //             'description' => "{$adminTenant['fullname']} added successfully",
    //             'module_accessed' => ListModuleEnums::Records
    //         ];
    //         GeneralHelper::storeAuditLog($dataToLog);

    //         // Commit landlord transaction only after all tenant setup is complete
    //         DB::connection('landlord')->commit();

    //         return JsonResponser::send(
    //             true,
    //             'Tenant onboarding completed successfully. Please check your email to verify your account.',
    //             [
    //                 'tenant' => $tenant,
    //                 'registration' => $registration,
    //                 'admin' => $adminTenant,
    //             ],
    //             200
    //         );
    //     } catch (\Exception $e) {
    //         DB::connection('landlord')->rollBack();
    //         if (!$isProduction) {
    //             DB::statement("DROP DATABASE IF EXISTS {$tenantDatabase}");
    //         }
    //         return JsonResponser::send(
    //             false,
    //             'An error occurred during tenant database setup: ' . $e->getMessage(),
    //             null,
    //             500
    //         );
    //     }
    //     // } catch (\Exception $e) {
    //     //     DB::connection('landlord')->rollBack();
    //     //     return JsonResponser::send(
    //     //         false,
    //     //         'An error occurred during tenant onboarding: ' . $e->getMessage(),
    //     //         null,
    //     //         500
    //     //     );
    //     // }
    // }



    public function onboardTenant(TenantOnboardingRequest $request)
    {
        DB::connection('landlord')->beginTransaction();

        try {
            $data = $request->validated();
            $adminRole = $this->roleService->getAdminRole();

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

            $registration = $this->registrationService->create($registrationData);

            $domain = Str::slug($data['name'], '-') . '.emed.com';
            $existingTenant = Tenant::where('domain', $domain)->first();
            if ($existingTenant) {
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(false, "Tenant {$data['name']} already exists.", [], 500);
            }

            $isProduction = true;
            // app()->environment(['production', 'staging', 'qa']);
            $tenantDatabase = $isProduction
                ? 'jkpmjemy_tenant_john_hospital'
                : 'tenant_' . Str::slug($data['name'], '_');

            //
            $tenant = Tenant::create([
                'name' => $data['name'],
                'domain' => $domain,
                'database' => $tenantDatabase,
            ]);

            dd(json_encode([$isProduction, $tenantDatabase, $tenant?->database]));

            // if ($isProduction === false) {
            //     DB::statement("CREATE DATABASE IF NOT EXISTS {$tenantDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            // }
            // else {
            //     $dbExists = DB::connection('landlord')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenantDatabase]);
            //     if (empty($dbExists)) {
            //         DB::connection('landlord')->rollBack();
            //         return JsonResponser::send(
            //             false,
            //             "Database {$tenantDatabase} does not exist. Please create it manually before onboarding this tenant.",
            //             null,
            //             500
            //         );
            //     }
            // }

            // $tenant->makeCurrent();
            // config(['database.connections.tenant.database' => $tenantDatabase]);
            DB::purge('tenant');
            Config::set('database.connections.tenant.database',  $tenant?->database);

            DB::reconnect('tenant');

            if (!Schema::connection('tenant')->hasTable('tenants')) {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            }

            $seeders = [
                'RolePermissionSeeder',
                'ServicesTableSeeder',
                'StateSeeder',
                'ServiceUnitSeeder',
                'UsersTableSeeder',
            ];

            foreach ($seeders as $seeder) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => $seeder,
                    '--force' => true,
                ]);
            }

            // DB::connection('tenant')->table('tenants')->insert([
            //     'id' => $tenant->id,
            //     'name' => $tenant->name,
            //     'domain' => $tenant->domain,
            //     'database' => $tenantDatabase,
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);

            $adminLandlord = User::on('landlord')->create([
                'uuid' => Str::uuid(),
                'fullname' => $data['admin_fullname'],
                'role' => $data['admin_role'],
                'phone_number' => $data['admin_phone_number'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'tenant_id' => $tenant->id,
                'remember_token' => Str::random(60),
            ]);

            $existingTenantUser = DB::connection('tenant')->table('users')->where('id', $adminLandlord->id)->first();

            if (!$existingTenantUser) {
                // DB::connection('tenant')->table('tenants')->insert([
                //     'id' => $tenant->id,
                //     'name' => $tenant->name,
                //     'domain' => $tenant->domain,
                //     'database' => $tenantDatabase,
                //     'created_at' => now(),
                //     'updated_at' => now(),
                // ]);
                //dd(json_encode($adminLandlord, JSON_PRETTY_PRINT));
                DB::connection('tenant')->table('users')->insert([
                    'id' => $adminLandlord->id,
                    'uuid' => $adminLandlord->uuid,
                    'fullname' => $adminLandlord->fullname,
                    'role' => $adminLandlord->role,
                    'phone_number' => $adminLandlord->phone_number,
                    'email' => $adminLandlord->email,
                    'password' => $adminLandlord->password,
                    'tenant_id' => $tenant->id,
                    'remember_token' => $adminLandlord->remember_token,
                ]);
            }
            // dd(json_encode($adminLandlord, JSON_PRETTY_PRINT));
            $adminTenant = User::on('tenant')->find($adminLandlord->id);
            $adminTenant->email = $data['admin_email'];
            $adminTenant->addRole($adminRole);
            $adminTenant->permissions()->sync($adminRole->permissions);

            $verificationCode = $adminTenant->remember_token;
            $verificationUrl = url('/verify-email/' . $verificationCode . '?email=' . urlencode($data['admin_email']));
            $adminTenant->save();

            Mail::to($adminTenant->email)->send(new TenantEmailVerification($verificationUrl, [
                'firstname' => $data['admin_fullname'],
                'email' => $data['admin_email'],
                'verification_code' => $verificationCode,
            ]));

            GeneralHelper::storeAuditLog([
                'causer_id' => $adminTenant->id,
                'action_id' => $adminTenant->id,
                'action_type' => "App\Models\User",
                'log_name' => "Tenant Created Successfully",
                'description' => "{$adminTenant['fullname']} added successfully",
                'module_accessed' => ListModuleEnums::Records
            ]);

            DB::connection('landlord')->commit();

            return JsonResponser::send(
                true,
                'Tenant onboarding completed successfully. Please check your email to verify your account.',
                [
                    'tenant' => $tenant,
                    'registration' => $registration,
                    'admin' => $adminTenant,
                ],
                200
            );
        } catch (\Exception $e) {
            DB::connection('landlord')->rollBack();

            // if (!empty($tenantDatabase) && !$isProduction) {
            //     DB::statement("DROP DATABASE IF EXISTS {$tenantDatabase}");
            // }

            if (!empty($tenantDatabase) && !$isProduction) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS {$tenantDatabase}");
                } catch (\Exception $dropEx) {
                    Log::warning("Failed to drop tenant DB {$tenantDatabase}: " . $dropEx->getMessage());
                }
            } elseif ($isProduction) {
                Log::info("Skipping DROP DATABASE for {$tenantDatabase} in production.");
            }


            return JsonResponser::send(
                false,
                'An error occurred during tenant database setup: ' . $e->getMessage(),
                null,
                500
            );
        }
    }


    //LOGIN::THROUGH LANDLORD DB

    // public function adminLogin(AdminLoginRequest $request)
    // {
    //     DB::connection('tenant')->beginTransaction();
    //     try {
    //         $credentials = $request->only('email', 'password');

    //         $landlordUser = User::on('landlord')->where('email', $credentials['email'])->first();
    //         if (!$landlordUser) {
    //             return JsonResponser::send(false, 'Invalid credentials', [], 401);
    //         }

    //         if (!$landlordUser->is_verified) {
    //             return JsonResponser::send(false, 'Your email has not been verified. Please check your email for verification.', [], 403);
    //         }

    //         if (!$token = JWTAuth::attempt($credentials)) {
    //             return JsonResponser::send(false, 'Invalid credentials', [], 401);
    //         }

    //         $tenant = Tenant::find($landlordUser->tenant_id);
    //         if (!$tenant) {
    //             JWTAuth::setToken($token)->invalidate();
    //             return JsonResponser::send(false, 'Tenant not found for this user', [], 404);
    //         }

    //         $tenant->makeCurrent();
    //         config(['database.connections.tenant.database' => $tenant->database]);
    //         DB::purge('tenant');
    //         DB::reconnect('tenant');

    //         $tenantUser = User::on('tenant')->with('roles.permissions')->find($landlordUser->id);
    //         if (!$tenantUser) {
    //             JWTAuth::setToken($token)->invalidate();
    //             return JsonResponser::send(false, 'User not found in tenant DB', [], 404);
    //         }

    //         $hospital = User::on('tenant')->where('tenant_id', $tenant->id)->first();
    //         if (!$hospital) {
    //             JWTAuth::setToken($token)->invalidate();
    //             return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
    //         }

    //         if (!$landlordUser->email_verified_at) {
    //             $landlordUser->update([
    //                 'email_verified_at' => now(),
    //                 'status' => 'active',
    //                 'is_verified' => true,
    //                 'can_login' => true,
    //                 'is_active' => true,
    //             ]);
    //         }

    //         $permissions = [];
    //         foreach ($tenantUser->roles as $role) {
    //             foreach ($role->permissions as $permission) {
    //                 $permissions[] = [
    //                     'id' => $permission->id,
    //                     'name' => $permission->name,
    //                     'slug' => $permission->slug,
    //                     'description' => $permission->description,
    //                     'created_at' => $permission->created_at ? $permission->created_at->toISOString() : null,
    //                     'updated_at' => $permission->updated_at ? $permission->updated_at->toISOString() : null,
    //                 ];
    //             }
    //         }

    //         DB::connection('tenant')->commit();

    //         return JsonResponser::send(
    //             true,
    //             'Admin logged in successfully',
    //             [
    //                 'user' => new UserResource($tenantUser),
    //                 'tenant' => $tenant,
    //                 'hospital_registration' => $hospital,
    //                 'roles' => $tenantUser->roles->pluck('name'),
    //                 'permissions' => $permissions,
    //                 'token' => $token,
    //             ],
    //             200
    //         );
    //     } catch (\Exception $e) {
    //         DB::connection('tenant')->rollBack();
    //         return JsonResponser::send(
    //             false,
    //             'An error occurred during login: ' . $e->getMessage(),
    //             null,
    //             500
    //         );
    //     }
    // }

    //LOGIN THROUGH INDIVIDUAL TENANT DB

    // public function adminLogin(AdminLoginRequest $request)
    // {
    //     DB::connection('tenant')->beginTransaction();
    //     try {
    //         $credentials = $request->only('email', 'password');

    //         // $tenantDomain = $request->input('tenant_domain');

    //         //  $fullHost = $request->getHost(); // e.g. danking-hospitals.emed.com
    //         // $mainDomain = config('app.central_domain', 'emed.com'); // set in config/app.php or .env

    //         // if (!str_ends_with($fullHost, $mainDomain)) {
    //         //     return JsonResponser::send(false, 'Invalid tenant domain.', [], 400);
    //         // }

    //         // $subdomain = str_replace('.' . $mainDomain, '', $fullHost); // e.g. danking-hospitals
    //         // $tenantDomain = $subdomain . '.' . $mainDomain;

    //         // if (!$tenantDomain) {
    //         //     return JsonResponser::send(false, 'Tenant domain is required.', [], 400);
    //         // }

    //         // $tenant = Tenant::where('domain', $tenantDomain)->first();
    //         // if (!$tenant) {
    //         //     return JsonResponser::send(false, 'Tenant not found.', [], 404);
    //         // }



    //         // An Error Occurred During Login. Undefined Variable $tenant

    //         $user = User::on('tenant')->where('email', $credentials['email'])->first();
    //         if (!$user) {
    //               DB::rollBack();
    //             return JsonResponser::send(false, 'Invalid credentials', [], 401);
    //         }

    //         $tenant =  $user?->tenant;
    //         // Tenant::find(tenant_id);
    //        if (!$tenant) {
    //         DB::rollBack();
    //         return JsonResponser::send(false, 'Tenant not found.', [], 404);
    //         }

    //         if (!$user->is_verified) {
    //               DB::rollBack();
    //             return JsonResponser::send(false, 'Your email has not been verified. Please check your email for verification.', [], 403);
    //         }

    //         if (!$token = JWTAuth::attempt($credentials)) {
    //               DB::rollBack();
    //             return JsonResponser::send(false, 'Invalid credentials', [], 401);
    //         }

    //         $hospital = User::on('tenant')->where('tenant_id', $user->tenant_id)->first();
    //         if (!$hospital) {
    //             JWTAuth::setToken($token)->invalidate();
    //               DB::rollBack();
    //             return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
    //         }

    //         //  dd(json_encode());

    //         // $tenant->makeCurrent();
    //         // config(['database.connections.tenant.database' => $tenant->database]);
    //         // DB::purge('tenant');
    //         // DB::reconnect('tenant');

    //         if (!$user->email_verified_at) {
    //             $user->update([
    //                 'email_verified_at' => now(),
    //                 'status' => 'active',
    //                 'is_verified' => true,
    //                 'can_login' => true,
    //                 'is_active' => true,
    //             ]);
    //         }

    //         $roles = $user->roles;
    //         $permissions = [];

    //         foreach ($roles as $role) {
    //             foreach ($role->permissions as $permission) {
    //                 $permissions[] = [
    //                     'id' => $permission->id,
    //                     'name' => $permission->name,
    //                     'slug' => $permission->slug,
    //                     'description' => $permission->description,
    //                     'model' => 'Permission',
    //                     'created_at' =>  $permission->created_at ? $permission->created_at->toISOString() : null,
    //                     'updated_at' =>  $permission->updated_at ? $permission->updated_at->toISOString() : null,
    //                     'deleted_at' => $permission->deleted_at ? $permission->deleted_at->toISOString() : null,
    //                     'is_active' => $permission->is_active ? 'true' : 'false',
    //                     'is_default' => $permission->is_default ? 'true' : 'false',
    //                     'pivot' => [
    //                         'role_id' => $role->id,
    //                         'permission_id' => $permission->id,
    //                         'created_at' => $permission->pivot->created_at ? $permission->pivot->created_at->toISOString() : null,
    //                         'updated_at' => $permission->pivot->updated_at ? $permission->pivot->updated_at->toISOString() : null,
    //                     ],
    //                 ];
    //             }
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



    public function adminLogin(AdminLoginRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $credentials = $request->only('email', 'password');

            // Attempt to find the user in the tenant database
            $user = User::on('tenant')->where('email', $credentials['email'])->first();

            if (!$user) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            $tenant = $user->tenant;

            if (!$tenant) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(false, 'Tenant not found.', [], 200);
            }

            if (!$user->is_verified) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(false, 'Your email has not been verified. Please check your email.', [], 403);
            }

            if (!$token = JWTAuth::attempt($credentials)) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }


            $hospital = $user->tenant;

            if (!$hospital) {
                JWTAuth::setToken($token)->invalidate();
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
            }

            $tenant->makeCurrent();
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            if (!$user->email_verified_at) {
                $user->update([
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'is_verified' => true,
                    'can_login' => true,
                    'is_active' => true,
                ]);
            }

            $roles = $user->roles;
            $permissions = [];

            foreach ($roles as $role) {
                foreach ($role->permissions as $permission) {
                    $permissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                        'model' => 'Permission',
                        'created_at' => optional($permission->created_at)->toISOString(),
                        'updated_at' => optional($permission->updated_at)->toISOString(),
                        'deleted_at' => optional($permission->deleted_at)->toISOString(),
                        'is_active' => (bool) $permission->is_active,
                        'is_default' => (bool) $permission->is_default,
                        'pivot' => [
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                            'created_at' => optional($permission->pivot->created_at)->toISOString(),
                            'updated_at' => optional($permission->pivot->updated_at)->toISOString(),
                        ],
                    ];
                }
            }

            DB::connection('tenant')->commit();

            return JsonResponser::send(
                true,
                'Admin logged in successfully',
                [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $roles->pluck('name'),
                        'permissions' => $permissions,
                    ],
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'domain' => $tenant->domain,
                    ],
                    'hospital_registration' => $hospital,
                    'token' => $token,
                ],
                200
            );
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(
                false,
                'An error occurred during Login. ' . $e->getMessage(),
                null,
                500
            );
        }
    }



    public function refreshToken()
    {
        config(['database.default' => 'tenant']);
        DB::connection('tenant')->beginTransaction();

        try {
            $user = auth()->user();

            if (!$user) {
                throw new \Exception("User not authenticated");
            }

            $newAccessToken = JWTAuth::fromUser($user);

            $data = [
                'token' => $newAccessToken,
                'expires_in' => auth()->factory()->getTTL() * 60,
            ];

            DB::connection('tenant')->commit();

            return JsonResponser::send(
                true,
                'New access token',
                $data,
                200
            );
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();

            return JsonResponser::send(
                false,
                $e->getMessage(),
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

    public function me()
    {
        try {
            $landlordUser = JWTAuth::parseToken()->authenticate();
            if (!$landlordUser) {
                return JsonResponser::send(false, 'User not authenticated', [], 401);
            }

            $tenant = \App\Models\Tenant::find($landlordUser->tenant_id);
            if (!$tenant) {
                return JsonResponser::send(false, 'Tenant not found', [], 200);
            }

            // Step 3: Set tenant context and switch DB
            // $tenant->makeCurrent();
            // config(['database.connections.tenant.database' => $tenant->database]);
            // DB::purge('tenant');
            // DB::reconnect('tenant');

            $tenantUser = User::on('tenant')->with('roles.permissions')->find($landlordUser->id);
            if (!$tenantUser) {
                return JsonResponser::send(false, 'User not found in tenant DB', [], 200);
            }

            $hospital = User::on('tenant')->where('tenant_id', $tenant->id)->first();
            if (!$hospital) {
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 200);
            }

            $permissions = $tenantUser->roles->flatMap(function ($role) {
                return $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                        'created_at' => optional($permission->created_at)->toISOString(),
                        'updated_at' => optional($permission->updated_at)->toISOString(),
                    ];
                });
            })->unique('id')->values();

            return JsonResponser::send(true, 'User information retrieved successfully', [
                'user' => new UserResource($tenantUser),
                'tenant' => $tenant,
                'hospital_registration' => $hospital,
                'roles' => $tenantUser->roles->pluck('name'),
                'permissions' => $permissions,
            ], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(
                false,
                'An error occurred while retrieving user information: ' . $e->getMessage(),
                null,
                500
            );
        }
    }


    public function check_is_change_password()
    {
        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();
        try {
            $user = Auth::user();
            $user_information =  $user->load('userInformation');
            return JsonResponser::send(
                true,
                'Your email has been verified. You can now log in.',
                $user_information,
                200
            );
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(
                false,
                'An error occurred while retrieving user information: ' . $th->getMessage(),
                null,
                500
            );
        }
    }


    public function user_information()
    {
        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();
        try {
            $user = Auth::user();
            $user_information =  $user->load('tenant.registration');
            return JsonResponser::send(
                true,
                'Your email has been verified. You can now log in.',
                $user_information,
                200
            );
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(
                false,
                'An error occurred while retrieving user information: ' . $th->getMessage(),
                null,
                500
            );
        }
    }

    public function change_password(ChangePasswordRequest $request)
    {
        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();
        try {
            $validated = $request->validated();
            $user = Auth::user();
            if ($user) {
                $user->update([
                    'is_change_password' => 1,
                    'password' => Hash::make($validated['password']),
                ]);

                DB::connection('tenant')->commit();
                DB::connection('landlord')->commit();
                return JsonResponser::send(
                    true,
                    'Password Changed Successfully.',
                    $user,
                    200
                );
            }
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(
                false,
                'An error occurred while retrieving user information: ' . $th->getMessage(),
                null,
                500
            );
        }
    }

    public function logout()
    {
        try {
            JWTAuth::parseToken()->invalidate();
            return JsonResponser::send(true, 'User logged out successfully', [], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(false, 'An error occurred during logout: ' . $e->getMessage(), null, 500);
        }
    }
}
