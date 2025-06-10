<?php

namespace App\Http\Controllers\v1\Auth;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{

    protected UserService $userService;

    public function __construct(
        UserService $userService,
    ) {
        $this->userService = $userService;
    }

    /**
     * Handle login and return an API token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function login(LoginRequest $request)
    // {
    //     $credentials = request(['email', 'password']);

    //     if (!$token = JWTAuth::attempt($credentials)) {
    //         return JsonResponser::send(true, 'Invalid email or password', [], 400);
    //     }

    //     // This will check if email has been verified
    //     $currentUser = auth()->user();

    //     if (!$currentUser->is_verified) {
    //         return JsonResponser::send(true, 'Account not verified. Kindly verify your email', [], 400);
    //     }

    //     // This will check if user has been deactivated
    //     if (!$currentUser->is_active) {
    //         return JsonResponser::send(true, 'Your account has been deactivated. Please contact the administrator', [], 400);
    //     }

    //     $user = $this->userService->find($currentUser->id);

    //     $user->update([
    //         "last_login" => now()
    //     ]);

    //     $data = [
    //         "user" => $user,
    //         'accessToken' => $token,
    //         'tokenType' => 'Bearer',
    //     ];

    //     $dataToLog = [
    //         'causer_id' => $user->id,
    //         'action_id' => $user['id'],
    //         'action_type' => "Models\User",
    //         'log_name' => "User logged in successfully",
    //         'description' => "{$user['firstname']} {$user['lastname']} logged in successfully",
    //     ];

    //     GeneralHelper::storeAuditLog($dataToLog);

    //     return JsonResponser::send(false, 'You are logged in successfully', $data);
    // }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            $currentUserInstance = auth()->user();

            $dataToLog = [
                'causer_id' => $currentUserInstance->id,
                'action_id' => $currentUserInstance->id,
                'action_type' => "Models\User",
                'log_name' => "User logged out successfully",
                'description' => "{$currentUserInstance->lastname} {$currentUserInstance->firstname} Logged out successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            // Invalidate the JWT token
            JWTAuth::invalidate(JWTAuth::getToken());

            return JsonResponser::send(false, 'Successfully logged out', null);
        } catch (\Throwable $error) {
            return JsonResponser::send(true, $error->getMessage(), [], 500, $error);
        }
    }

    public function me()
    {
        try {
            $landlordUser = JWTAuth::parseToken()->authenticate();
            if (!$landlordUser) {
                return JsonResponser::send(false, 'User not authenticated', [], 401);
            }

            $tenant = Tenant::find($landlordUser->tenant_id);
            if (!$tenant) {
                return JsonResponser::send(false, 'Tenant not found for this user', [], 404);
            }

            $tenant->makeCurrent();
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $tenantUser = User::on('tenant')->with('roles.permissions')->find($landlordUser->id);
            if (!$tenantUser) {
                return JsonResponser::send(false, 'User not found in tenant DB', [], 404);
            }

            $hospital = User::on('tenant')->where('tenant_id', $tenant->id)->first();
            if (!$hospital) {
                return JsonResponser::send(false, 'No hospital information found for this tenant', [], 404);
            }

            $permissions = [];
            foreach ($tenantUser->roles as $role) {
                foreach ($role->permissions as $permission) {
                    $permissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                        'created_at' => $permission->created_at ? $permission->created_at->toISOString() : null,
                        'updated_at' => $permission->updated_at ? $permission->updated_at->toISOString() : null,
                    ];
                }
            }

            return JsonResponser::send(
                true,
                'User information retrieved successfully',
                [
                    'user' => new UserResource($tenantUser),
                    'tenant' => $tenant,
                    'hospital_registration' => $hospital,
                    'roles' => $tenantUser->roles->pluck('name'),
                    'permissions' => $permissions,
                ],
                200
            );
        } catch (\Exception $e) {
            return JsonResponser::send(
                false,
                'An error occurred while retrieving user information: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
