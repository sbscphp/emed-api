<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Responser\JsonResponser;
use App\Services\Role\RoleService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    protected $userService;
    protected RoleService $roleService;

    public function __construct(UserService $userService, RoleService $roleService)
    {
        $this->userService = $userService;
        $this->roleService = $roleService;
    }

    /**
     * Fetch all roles.
     */
    public function index()
    {
        $roles = $this->roleService->all()->load('permissions', 'users');

        if ($roles->isEmpty()) {
            return JsonResponser::send(true, 'No roles found.', null, 404);
        }

        $totalRoles = $roles->count();

        $rolesData = $roles->map(function ($role) {
            return [
                'id' => $role->id ?? "",
                'name' => $role->name ?? "",
                'display_name' => $role->display_name ?? "",
                'description' => $role->description ?? "",
                'status' => $role->status ?? "",
                'permissions' => $role->permissions->pluck('name')->toArray() ?? [],
                'users_count' => $role->users->count() ?? 0,
                'created_at' => $role->created_at ?? "",
                'updated_at' => $role->updated_at ?? "",
            ];
        });

        return JsonResponser::send(false, 'Roles retrieved successfully.', [
            'roles' => $rolesData,
            'total_roles' => $totalRoles,
        ], 200);
    }

    /**
     * Create a new role and assign permissions
     */
    public function store(StoreRoleRequest $request)
    {
        $currentUser = Auth::user();
        $user = $this->userService->find($currentUser->id);

        $validated = array_merge($request->validated(), [
            'created_by' => $currentUser->id,
        ]);

        try {
            $role = DB::connection('tenant')->transaction(function () use ($validated, $request, $user) {
                $role = $this->roleService->create($validated);

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }

                $dataToLog = [
                    'causer_id' => $user->id,
                    'action_id' => $role->id,
                    'action' => 'Create',
                    'action_type' => "Models\Role",
                    'log_name' => "Role created successfully",
                    'description' => "{$user->firstname} {$user->lastname} created a new role: {$role->name}",
                ];
                GeneralHelper::storeAuditLog($dataToLog);

                return $role;
            });

            return JsonResponser::send(false, 'Role created successfully with permissions.', $role, 201);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to create role. Please try again.', null, 500);
        }
    }


    /**
     * Fetch a single role by ID.
     */
    public function show($id)
    {
        $role = $this->roleService->find($id)->load(['permissions', 'users']);

        if (!$role) {
            return JsonResponser::send(true, 'Role not found.', null, 404);
        }

        $usersData = $role->users->map(function ($user) {
            return [
                'name' => $user->fullname,
                'status' => $user->status,
                'created_at' => $user->created_at ? $user->created_at->toDateTimeString() : null,
            ];
        });

        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'status' => $role->status,
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'users' => $usersData,
            'created_at' =>  $role->created_at ? $role->created_at->toDateTimeString() : null,
            'updated_at' =>   $role->created_at ? $role->updated_at->toDateTimeString() : null,
        ];

        return JsonResponser::send(false, 'Role retrieved successfully.', $roleData, 200);
    }


    /**
     * Update an existing role and reassign permissions.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        $currentUser = Auth::user();
        $user = $this->userService->find($currentUser->id);

        $validated = array_merge($request->validated(), [
            'updated_by' => $currentUser->id,
        ]);

        try {
            $role = DB::connection('tenant')->transaction(function () use ($validated, $request, $id, $user) {
                $role = $this->roleService->update($validated, $id);

                if (!$role) {
                    return JsonResponser::send(true, 'Role not found or update failed.', null, 404);
                }

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }

                $dataToLog = [
                    'causer_id' => $user->id,
                    'action_id' => $role->id,
                    'action' => 'Update',
                    'action_type' => "Models\Role",
                    'log_name' => "Role updated successfully",
                    'description' => "{$user->firstname} {$user->lastname} updated the role: {$role->name}",
                ];
                GeneralHelper::storeAuditLog($dataToLog);

                return $role;
            });



            return JsonResponser::send(false, 'Role updated successfully with permissions.', $role, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to update role. Please try again.', null, 500);
        }
    }


    /**
     * Delete a role.
     */
    public function destroy($id)
    {
        $role = $this->roleService->find($id);

        if (!$role) {
            return JsonResponser::send(true, 'Role not found.', null, 404);
        }

        if ($role->users()->count() > 0) {
            return JsonResponser::send(true, 'Cannot delete role with assigned users.', null, 400);
        }

        $this->roleService->delete($id);

        return JsonResponser::send(false, 'Role deleted successfully.', null, 200);
    }
}
