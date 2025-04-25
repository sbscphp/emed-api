<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Responser\JsonResponser;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class UserController extends Controller
{
    protected $userService;

    public function __construct(
        UserService $userService,
    ) {
        $this->userService = $userService;
    }

    public function allUsers()
    {
        try {
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $users = $this->userService->all();

            if ($users->isEmpty()) {
                return JsonResponser::send(true, 'No users found.', null, 404);
            }

            return JsonResponser::send(false, 'Users retrieved successfully.', [
                'records' => $users,
                'total' => $users->count()
            ], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function addUser(StoreUserRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole(['super_admin', 'admin'])) {
                return JsonResponser::send(true, 'Unauthorized action.', [], 403);
            }

            $data = $request->validated();

            $role = Role::where('name', $data['role'])->first();
            if (!$role) {
                return JsonResponser::send(true, 'Invalid role provided.', [], 422);
            }

            $data['tenant_id'] = $currentUser->tenant_id;
            $data['uuid'] = (string) Str::uuid();
            $data['email_verified_at'] = now();
            $data['can_login'] = 1;
            $data['is_verified'] = 1;
            $data['is_active'] = 1;
            $data['password'] = Hash::make($data['password']);

            $user = $this->userService->create($data);

            $user->roles()->attach($role->id);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $user->id,
                'action' => 'Create',
                'action_type' => "Models\User",
                'log_name' => "User created successfully",
                'description' => "{$currentUser->first_name} {$currentUser->last_name} created a new user: {$user->first_name} {$user->last_name}",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();

            return JsonResponser::send(false, 'User created successfully.', $user, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }


    public function viewUser($id)
    {
        try {
            $user = $this->userService->find($id);

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            return JsonResponser::send(false, 'User retrieved successfully.', $user, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function updateUser(UpdateUserRequest $request, $id)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole(['super_admin', 'admin'])) {
                return JsonResponser::send(true, 'Unauthorized action.', [], 403);
            }

            $user = $this->userService->find($id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $data = $request->validated();

            if (isset($data['role'])) {
                $role = Role::where('name', $data['role'])->first();
                if (!$role) {
                    return JsonResponser::send(true, 'Invalid role provided.', [], 422);
                }

                $user->roles()->sync([$role->id]);
            }

            unset($data['role']);

            $updatedUser = $this->userService->update($data, $id);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $user->id,
                'action' => 'Update',
                'action_type' => "Models\User",
                'log_name' => "User updated successfully",
                'description' => "{$currentUser->first_name} {$currentUser->last_name} updated user: {$user->first_name} {$user->last_name}",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();

            return JsonResponser::send(false, 'User updated successfully.', $updatedUser, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }


    public function deleteUser($id)
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole(['super_admin', 'admin'])) {
                return JsonResponser::send(true, 'Unauthorized action.', [], 403);
            }

            $user = $this->userService->find($id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $this->userService->delete($id);

            return JsonResponser::send(false, 'User deleted successfully.', [], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
