<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\TenantOnboardingRequest;
use App\Mail\TenantEmailVerification;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Registration\RegistrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
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

    public function onboardTenant(TenantOnboardingRequest $request)
    {
        try {
            // $user = Auth::user();

            // if (!$user->hasRole(['Super Admin', 'Admin'])) {
            //     return JsonResponser::send(false, 'Permission denied. Only admins can onboard a tenant.', [], 403);
            // }

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

                Artisan::call('migrate', [
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
                    'password' => $data['admin_password'],
                    'remember_token' => Str::random(60),
                    'tenant_id' => $tenant->id,
                ];

                $admin = $this->registrationService->saveAdminDetails($adminData, $tenant->id);
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
                DB::statement("DROP DATABASE IF EXISTS {$tenant->database}");
                return JsonResponser::send(
                    false,
                    'An error occurred during tenant database: ' . $e->getMessage(),
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

    public function adminLogin(AdminLoginRequest $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            // Check if user is verified
            if (!$user->is_verified) {
                return JsonResponser::send(false, 'Your email has not been verified. Please check your email for verification.', [], 403);
            }

            if (!$token = JWTAuth::attempt($credentials)) {
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            $tenant = Tenant::find($user->tenant_id);
            if (!$tenant) {
                JWTAuth::setToken($token)->invalidate();
                return JsonResponser::send(false, 'Tenant not found for this user', [], 404);
            }

            $hospital = User::where('tenant_id', $tenant->id)->first();
            if (!$hospital) {
                JWTAuth::setToken($token)->invalidate();
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
            }

            // Updating user fields when logging in for the first time after verification
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

            $user = User::where('remember_token', $token)->first();

            if (!$user) {
                return JsonResponser::send(
                    false,
                    'Invalid or expired verification link.',
                    null,
                    404
                );
            }

            $user->update([
                'is_verified' => true,
                'email_verified_at' => now(),
                'status' => 'active',
                'can_login' => true,
                'is_active' => true,
                'remember_token' => null,
            ]);

            DB::commit();

            return JsonResponser::send(
                true,
                'Your email has been verified. You can now log in.',
                [
                    'user' => [
                        'email' => $user->email,
                    ]
                ],
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return JsonResponser::send(
                false,
                'An error occurred while verifying your email. Please try again later ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
