<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\TenantOnboardingRequest;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegistrationController extends Controller
{
    protected RegistrationService $registrationService;

    public function __construct(RegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function onboardTenant(TenantOnboardingRequest $request)
    {
        try {
            DB::connection('landlord')->beginTransaction();

            $data = $request->validated();

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
                'database' => 'tenant_' . Str::slug($data['name'], '_') . '_' . Str::random(4),
            ]);

            DB::connection('landlord')->commit();

            try {
                DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $tenant->makeCurrent();

                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                $output = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);


                $adminData = [
                    'uuid' => Str::uuid(),
                    'fullname' => $data['admin_fullname'],
                    'role' => $data['admin_role'],
                    'phone_number' => $data['admin_phone_number'],
                    'email' => $data['admin_email'],
                    'password' => Hash::make($data['admin_password']),
                    'tenant_id' => $tenant->id,
                ];

                $admin = $this->registrationService->saveAdminDetails($adminData, $tenant->id);

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
                    'Tenant onboarding completed successfully.',
                    [
                        'tenant' => $tenant,
                        'registration' => $registration,
                        'admin' => $admin,
                    ],
                    200
                );
            } catch (\Exception $e) {
                DB::statement("DROP DATABASE IF EXISTS {$tenant->database}");

                Log::error('Error during tenant-specific operations: ' . $e->getMessage());

                throw $e;
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

    public function adminLogin(AdminLoginRequest $request)
    {
        try {
            // Extract credentials
            $credentials = $request->only('email', 'password');

            // Find the user in the landlord database
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return JsonResponser::send(false, 'User not found', [], 404);
            }

            // Switch to the tenant's database
            $tenant = Tenant::find($user->tenant_id);
            if (!$tenant) {
                return JsonResponser::send(false, 'Tenant not found for this user', [], 404);
            }

            // Configure the tenant database connection
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            // Make the tenant current
            $tenant->makeCurrent();

            if (!$token = JWTAuth::attempt($credentials)) {
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            $user = JWTAuth::user();

            $hospital = \App\Models\User::where('tenant_id', $tenant->id)->first();

            if (!$hospital) {
                JWTAuth::setToken($token)->invalidate();
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
            }

            // Return success response
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
            Log::error('Error during admin login: ' . $e->getMessage());
            return JsonResponser::send(
                false,
                'An error occurred during Login.',
                null,
                500
            );
        }
    }
}
