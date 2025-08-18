<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Responser\JsonResponser;
use App\Services\Role\RoleService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Http\Request;

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
    public function index(Request $request)
    {
        $query = $this->roleService->all()->orderBy('id', 'DESC');

        // Count total roles (before pagination)
        $totalRoles = $query->count();

        // Apply pagination or get all
        if ($request->paginate) {
            $rolesRecord = $query->paginate($request->limit ?? 10);
        } else {
            $rolesRecord = $query->get();
        }

        // Eager load related models
        if (
            $rolesRecord instanceof \Illuminate\Pagination\LengthAwarePaginator ||
            $rolesRecord instanceof \Illuminate\Pagination\Paginator
        ) {

            // Load relationships
            $rolesRecord->getCollection()->load('permissions', 'users');

            // Transform the items while keeping pagination metadata
            $rolesRecord->setCollection(
                $rolesRecord->getCollection()->transform(function ($role) {
                    return [
                        'id'           => $role->id ?? "",
                        'name'         => $role->name ?? "",
                        'display_name' => $role->display_name ?? "",
                        'description'  => $role->description ?? "",
                        'status'       => $role->status ?? "",
                        'permissions'  => $role->permissions->pluck('name')->toArray() ?? [],
                        'users_count'  => $role->users->count() ?? 0,
                        'created_at'   => $role->created_at ?? "",
                        'updated_at'   => $role->updated_at ?? "",
                    ];
                })
            );
        } else {
            // If not paginating (just getting all)
            $rolesRecord->load('permissions', 'users');
            $rolesRecord = $rolesRecord->map(function ($role) {
                return [
                    'id'           => $role->id ?? "",
                    'name'         => $role->name ?? "",
                    'display_name' => $role->display_name ?? "",
                    'description'  => $role->description ?? "",
                    'status'       => $role->status ?? "",
                    'permissions'  => $role->permissions->pluck('name')->toArray() ?? [],
                    'users_count'  => $role->users->count() ?? 0,
                    'created_at'   => $role->created_at ?? "",
                    'updated_at'   => $role->updated_at ?? "",
                ];
            });
        }

        return JsonResponser::send(false, 'Roles retrieved successfully.', [
            'roles'       => $rolesRecord, // will include pagination data if paginated
            'total_roles' => $totalRoles,
        ], 200);
    }


    public function permissions()
    {
        try {
            DB::connection('tenant')->beginTransaction();

            // Ensure the tenant context is set before querying
            if (isset($this->tenant) && method_exists($this->tenant, 'makeCurrent')) {
                $this->tenant->makeCurrent();
            }

            // Fetch permissions and group by sub_module
            $records = Permission::all()->groupBy('sub_module');

            DB::connection('tenant')->commit();

            return JsonResponser::send(false, 'Permissions fetched successfully', $records, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Create a new role and assign permissions
     */
    public function store(StoreRoleRequest $request)
    {
        $currentUser = Auth::user();
        // $user = $this->userService->find($currentUser->id);
        $user = User::on('tenant')->where('email', $currentUser['email'])->first();

        $validated = array_merge($request->validated(), [
            'created_by' => $currentUser->id,
        ]);

        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();

        try {
            $tenantRole = new Role($validated);
            $tenantRole->setConnection('tenant');
            $tenantRole->save();

            $landlordRole = new Role();
            $landlordRole->setConnection('landlord');
            $landlordRole->fill($validated);
            $landlordRole->id = $tenantRole->id;
            $landlordRole->save();

            if ($request->has('permissions')) {
                $tenantRole->syncPermissions($request->permissions);
            }

            GeneralHelper::storeAuditLog([
                'causer_id' => $user->id,
                'action_id' => $tenantRole->id,
                'action' => 'Create',
                'action_type' => "Models\Role",
                'log_name' => "Role created successfully",
                'description' => "{$user->fullname} created a new role: {$tenantRole->name}",
                'module_accessed' => ListModuleEnums::Records
            ]);

            DB::connection('tenant')->commit();
            DB::connection('landlord')->commit();

            return JsonResponser::send(false, 'Role created successfully with permissions.', $tenantRole, 201);
        } catch (\Throwable $e) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
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
            return JsonResponser::send(true, 'Role not found.', null, 200);
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
        // $user = $this->userService->find($currentUser->id);
        $user = User::on('tenant')->where('email', $currentUser['email'])->first();

        $validated = array_merge($request->validated(), [
            'updated_by' => $currentUser->id,
        ]);

        try {
            $role = DB::connection('tenant')->transaction(function () use ($validated, $request, $id, $user) {
                $role = $this->roleService->update($validated, $id);

                if (!$role) {
                    return JsonResponser::send(true, 'Role not found or update failed.', null, 200);
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
                    'module_accessed' => ListModuleEnums::Records
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
            return JsonResponser::send(true, 'Role not found.', null, 200);
        }

        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();

        try {
            $role->users()->detach();

            $role->delete();

            $landlordRole = Role::on('landlord')->find($id);
            if ($landlordRole) {
                $landlordRole->users()->detach();
                $landlordRole->delete();
            }

            DB::connection('tenant')->commit();
            DB::connection('landlord')->commit();

            return JsonResponser::send(false, 'Role and associated user mappings deleted successfully.', null, 200);
        } catch (\Throwable $e) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();

            return JsonResponser::send(true, 'Failed to delete role. Please try again.', null, 500);
        }
    }
}
