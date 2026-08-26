<?php

namespace App\Http\Controllers\v1\SuperAdmin\Usermanagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AddUsersRequest;
use App\Http\Requests\SuperAdmin\CreateUserRequest;
use App\Http\Requests\SuperAdmin\SuperAdminRoleRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\UserManagement\UserManagementService;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    protected UserManagementService $userService;

    public function __construct(UserManagementService $userService)
    {
        $this->userService = $userService;
    }

    // ── Users ──

    public function users(Request $request)
    {
        try {
            $overview = $this->userService->usersOverview($request);
            $stats = $this->userService->userStats($request);

            $records = [
                ...$stats,
                'data' => $overview,
            ];

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createUser(CreateUserRequest $request)
    {
        try {
            $record = $this->userService->createUser($request);

            return JsonResponser::send(false, 'User created successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function showUser($id)
    {
        try {
            $record = $this->userService->showUser($id);

            if (!$record) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateUser($id, UpdateUserRequest $request)
    {
        try {
            $record = $this->userService->updateUser($id, $request);

            return JsonResponser::send(false, 'User updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function toggleUserStatus($id)
    {
        try {
            $record = $this->userService->toggleUserStatus($id);

            return JsonResponser::send(false, 'User status updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function removeUser($id)
    {
        try {
            $this->userService->removeUser($id);

            return JsonResponser::send(false, 'User removed successfully');
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    // ── Roles ──

    public function roles(Request $request)
    {
        try {
            $records = $this->userService->rolesOverview($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createRole(SuperAdminRoleRequest $request)
    {
        try {
            $record = $this->userService->createRole($request);

            return JsonResponser::send(false, 'Role created successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function showRole($id)
    {
        try {
            $record = $this->userService->showRole($id);

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateRole($id, SuperAdminRoleRequest $request)
    {
        try {
            $record = $this->userService->updateRole($id, $request);

            return JsonResponser::send(false, 'Role updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function toggleRoleStatus($id)
    {
        try {
            $record = $this->userService->toggleRoleStatus($id);

            return JsonResponser::send(false, 'Role status updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function deleteRole($id)
    {
        try {
            $this->userService->deleteRole($id);

            return JsonResponser::send(false, 'Role deleted successfully');
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
