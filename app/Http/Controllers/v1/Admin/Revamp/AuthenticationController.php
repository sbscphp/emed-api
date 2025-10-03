<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Revamp\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function verifyEmail(Request $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->authenticationService->verifyEmail($request->all());

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
                    ->get(['id', 'name', 'display_name']);

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
