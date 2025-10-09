<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Mail\TenantEmailVerification;
use App\Models\Tenant;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Revamp\AuthenticationService;
use App\Services\Revamp\PermissionAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationController extends Controller
{
    protected AuthenticationService $authenticationService;

    public function __construct(
        AuthenticationService $authenticationService,
    ) {
        $this->authenticationService = $authenticationService;
    }

    public function register(RegisterRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->authenticationService->create($request->all());

            DB::commit();
            return JsonResponser::send(false, 'Registration successful, please check your mail to verify your email.', $record, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function resendOtp(Request $request)
    {
        try {
            DB::beginTransaction();

            $validateRequest = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validateRequest->fails()) {
                return JsonResponser::send(true, $validateRequest->errors()->first(), $validateRequest->errors()->all(), 400);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return JsonResponser::send(true, 'Email address not found.', [], 400);
            }

            DB::connection('landlord')->table('password_reset_tokens')->where('email', $request->email)->delete();

            $otp = random_int(100000, 999999);
            $expiresAt = Carbon::now()->addMinutes(30);

            DB::connection('landlord')->table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'otp' => $otp,
                    'created_at' => now(),
                    'expires_at' => $expiresAt
                ]
            );

            $maildata = [
                'email' => $user->email,
                'name' => $user->first_name . ' ' . $user->last_name,
                'token' => $otp,
            ];

            Mail::to($request->email)->send(new TenantEmailVerification($maildata));

            DB::commit();
            return JsonResponser::send(false, 'Requested otp has been sent to the email associated with your account.', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $validateRequest = Validator::make($request->all(), [
                'otp' => 'required|numeric',
            ]);

            if ($validateRequest->fails()) {
                return JsonResponser::send(true, $validateRequest->errors()->first(), $validateRequest->errors()->all(), 400);
            }

            DB::beginTransaction();

            $tokenRecord = DB::connection('landlord')
                ->table('password_reset_tokens')
                ->where('otp', $request->otp)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$tokenRecord) {
                DB::rollBack();
                return JsonResponser::send(true, 'Invalid or expired OTP.', [], 400);
            }

            $user = User::find($tokenRecord->user_id);
            if (!$user) {
                DB::rollBack();
                return JsonResponser::send(true, 'User not found.', [], 404);
            }

            $user->email_verified_at = Carbon::now();
            $user->remember_token = null;
            $user->is_verified = 1;
            $user->is_completed = 1;
            $user->save();

            DB::connection('landlord')
                ->table('password_reset_tokens')
                ->where('email', $tokenRecord->email)
                ->update([
                    'status' => 'Verified',
                    'verified_at' => Carbon::now(),
                ]);

            DB::commit();

            return JsonResponser::send(false, 'OTP verified successfully.', [
                'email' => $user->email,
                'verified_at' => $user->email_verified_at,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500);
        }
    }

    // public function resendEmailVerification(Request $request)
    // {
    //     try {
    //         DB::beginTransaction();
    //         $record = $this->authenticationService->resendEmailVerification($request);
    //         DB::commit();
    //         return JsonResponser::send(false, "Verification email resent to {$record->email}.", 200);
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
    //     }
    // }

    public function verifyEmail(Request $request, $token, $email)
    {
        try {
            DB::beginTransaction();
            $record = $this->authenticationService->verifyEmail($token, $email);

            DB::commit();
            return JsonResponser::send(false, 'Email verified successfully.', $record, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function findHospitals(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return JsonResponser::send(true, 'No user found with this email.', [], 400);
            }

            $hospitals = $user->tenants()->select('name', 'uuid')->get();
            if ($hospitals->isEmpty()) {
                return JsonResponser::send(true, 'No hospital associated with this email.', [], 400);
            }
            return JsonResponser::send(false, 'Record found.', $hospitals, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'       => 'required|email',
                'password'    => 'required',
                'tenant_uuid' => 'nullable|exists:tenants,uuid',
            ]);

            $credentials = $request->only(['email', 'password']);

            $tenant = null;
            if ($request->filled('tenant_uuid')) {
                $tenant = Tenant::where('uuid', $request->tenant_uuid)->first();

                if (!$tenant) {
                    return JsonResponser::send(true, 'Invalid hospital selected.', [], 400);
                }

                // Switch to tenant DB
                $tenant->makeCurrent();
            } else {
                DB::purge('tenant');
                DB::setDefaultConnection('landlord');
            }

            if (!$token = JWTAuth::attempt($credentials)) {
                return JsonResponser::send(true, 'Invalid email or password', [], 400);
            }

            $currentUser = auth()->user();

            if (!$currentUser->is_verified) {
                return JsonResponser::send(true, 'Account not verified. Kindly verify your email.', [], 403);
            }

            if (!$currentUser->can_login) {
                return JsonResponser::send(true, 'Your account has been deactivated. Please contact the administrator.', [], 403);
            }

            if ($tenant && !$currentUser->tenants->contains($tenant->id)) {
                return JsonResponser::send(true, 'You are not registered with this hospital.', [], 403);
            }

            $user = $currentUser->toArray();

            if ($tenant) {
                $roles = $currentUser->roles()
                    ->where('roles.tenant_id', $tenant->uuid)
                    // ->with('permissions')
                    ->get(['id', 'name', 'display_name']);

                $currentRole = $roles->first();

                // Tenant user pivot from landlord DB
                $tenantUser = DB::connection('landlord')->table('tenant_users')
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $currentUser->id)
                    ->first();

                $user['current_tenant'] = [
                    'id'   => $tenant->id,
                    'uuid' => $tenant->uuid,
                    'name' => $tenant->name,
                ];
                $user['current_tenant_user']  = $tenantUser;
                $user['roles'] = $roles;
                $user['current_role'] = $currentRole ? [
                    'id'           => $currentRole->id,
                    'name'         => $currentRole->name,
                    'display_name' => $currentRole->display_name,
                ] : null;
            } else {
                // SUPER ADMIN LOGIN (landlord only)
                $roles = DB::connection('landlord')->table('roles')
                    ->join('role_user', 'roles.id', '=', 'role_user.role_id')
                    ->where('role_user.user_id', $currentUser->id)
                    ->get(['roles.id', 'roles.name', 'roles.display_name']);

                $user['current_tenant']       = null;
                $user['current_tenant_user']  = null;
                $user['roles'] = $roles;
            }

            // --- Inject Permissions ---
            $permissionService = app(PermissionAccessService::class);
            $permissions = $permissionService->allPermissions();

            $user['permissions'] = $permissions;

            return JsonResponser::send(false, 'Login successful.', [
                'user'        => $user,
                'accessToken' => $token,
                'tokenType'   => 'Bearer',
            ], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 500);
        }
    }
}
