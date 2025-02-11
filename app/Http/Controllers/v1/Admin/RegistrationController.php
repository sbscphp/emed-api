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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
            DB::beginTransaction();

            $data = $request->validated();

            $tenantData = [
                'name' => $data['name'],
            ];

            $tenant = \App\Models\Tenant::create($tenantData);

            if (empty($tenant->domain)) {
                $tenant->domain = Str::slug($tenant->name, '-') . '.emed.com';
                $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
                $tenant->save();
            }

            $registrationData = [
                'tenant_id' => $tenant->id,
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

            $registration = \App\Models\Registration::create($registrationData);

            if ($request->hasFile('license')) {
                $hospitalData['license'] = FileUploadHelper::singleBinaryFileUpload(
                    $request->file('license'),
                    'License'
                );
            }

            $adminData = [
                'fullname' => $data['admin_fullname'],
                'role' => $data['admin_role'],
                'phone_number' => $data['admin_phone_number'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ];


            $admin = $this->registrationService->saveAdminDetails($adminData, $tenant->id, $registration->id);
            $dataToLog = [
                'causer_id' => $admin->id,
                'action_id' => $admin->id,
                'action_type' => "App\Models\User",
                'log_name' => "User registered successfully and sent for approval",
                'description' => "{$admin['fullname']} added successfully",
            ];
            GeneralHelper::storeAuditLog($dataToLog);

            DB::commit();

            return JsonResponser::send(
                true,
                'Tenant onboarding completed successfully. A verification email has been sent',
                [
                    'tenant' => $tenant,
                    'registration' => $registration,
                    'admin' => $admin,
                ],
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return JsonResponser::send(
                false,
                'An error occurred during tenant onboarding.',
                null,
                500
            );
        }
    }

    public function adminLogin(AdminLoginRequest $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            if (!$token = JWTAuth::attempt($credentials)) {
                return JsonResponser::send(false, 'Invalid credentials', [], 401);
            }

            $user = JWTAuth::user();

            // Optionally check the admin role if needed
            // if ($user->role !== 'Administrator') {
            //     JWTAuth::setToken($token)->invalidate(); // Invalidate the token
            //     return response()->json([
            //         'error' => true,
            //         'message' => 'You do not have permission to log in as an admin.',
            //     ], 403);
            // }

            $tenant = Tenant::find($user->tenant_id);

            if (!$tenant) {
                JWTAuth::setToken($token)->invalidate();
                return JsonResponser::send(false, 'Tenant not found for this user', [], 404);
            }

            // if (!$user->is_active || !$user->is_verified) {
            //     JWTAuth::setToken($token)->invalidate();
            //     return JsonResponser::send(false, 'Your account is not active or verified', [], 403);
            // }

            $hospital = User::where('tenant_id', $tenant->id)->first();

            if (!$hospital) {
                JWTAuth::setToken($token)->invalidate();
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
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
            Log::info($e);
            return JsonResponser::send(
                false,
                'An error occurred during Login.',
                null,
                500
            );
        }
    }
}
