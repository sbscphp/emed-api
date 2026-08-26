<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
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
        $tenantUuid = $request->header('X-Tenant-ID');
        $export = $request->export;

        // Fetch tenant
        $tenant = Tenant::where('uuid', $tenantUuid)->first();
        if (! $tenant) {
            return JsonResponser::send(true, 'Invalid tenant.', null, 400);
        }

        $query = $this->roleService
            ->all($tenantUuid, $request)
            ->orderBy('id', 'DESC');

        $totalRoles = $query->count();
        $roles = $export
            ? $query->get()
            : ($request->paginate
                ? $query->paginate($request->limit ?? 10)
                : $query->get());
        $processRole = function ($role) use ($tenant, $tenantUuid) {

            $userIds = DB::connection('tenant')
                ->table('role_user')
                ->where('role_id', $role->id)
                ->pluck('user_id')
                ->toArray();

            if (empty($userIds)) {
                $role->correct_user_count = 0;
                return $role;
            }

            $validUserCount = \App\Models\User::whereIn('id', $userIds)
                ->whereHas('tenantUsers', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id)
                        ->whereNull('deleted_at');
                })
                ->count();

            $role->correct_user_count = $validUserCount;
            return $role;
        };

        // If paginated
        if (
            $roles instanceof \Illuminate\Pagination\Paginator ||
            $roles instanceof \Illuminate\Pagination\LengthAwarePaginator
        ) {

            $roles->getCollection()->transform($processRole);
        } else {
            // Non-paginated
            $roles = $roles->map($processRole);
        }

        if ($export) {

            $exportData = $roles->map(function ($role) {
                return [
                    'Role Name'   => $role->display_name ?? $role->name,
                    'Users Count' => $role->correct_user_count,
                ];
            })->toArray();

            return match (strtolower($export)) {
                'csv' => ExportHelper::streamCsv($exportData, null, 'roles.csv'),
                'pdf' => ExportHelper::downloadPdf($exportData, 'roles.pdf'),
                default => throw new \Exception('Invalid export format.'),
            };
        }

        if (
            $roles instanceof \Illuminate\Pagination\Paginator ||
            $roles instanceof \Illuminate\Pagination\LengthAwarePaginator
        ) {

            $roles->setCollection(
                $roles->getCollection()->map(function ($role) {
                    return [
                        'id'           => $role->id,
                        'name'         => $role->name,
                        'display_name' => $role->display_name,
                        'description'  => $role->description,
                        'status'       => $role->status,
                        'permissions'  => $role->permissions->pluck('name')->toArray(),
                        'users_count'  => $role->correct_user_count,
                        'created_at'   => $role->created_at,
                        'updated_at'   => $role->updated_at,
                    ];
                })
            );
        } else {
            $roles = $roles->map(function ($role) {
                return [
                    'id'           => $role->id,
                    'name'         => $role->name,
                    'display_name' => $role->display_name,
                    'description'  => $role->description,
                    'status'       => $role->status,
                    'permissions'  => $role->permissions->pluck('name')->toArray(),
                    'users_count'  => $role->correct_user_count,
                    'created_at'   => $role->created_at,
                    'updated_at'   => $role->updated_at,
                ];
            });
        }

        return JsonResponser::send(false, 'Roles retrieved successfully.', [
            'roles'       => $roles,
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
        $checkRole = Role::on('tenant')->where('name', $request->name)->first();
        if ($checkRole) {
            return JsonResponser::send(true, 'Role with the same name already exists.', null, 400);
        }
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
    public function show(Request $request, $id)
    {
        $tenantUuid = $request->header('X-Tenant-ID');

        $tenant = Tenant::where('uuid', $tenantUuid)->first();
        if (! $tenant) {
            return JsonResponser::send(true, 'Invalid tenant.', null, 400);
        }

        $role = \App\Models\Role::on('tenant')
            ->where('tenant_id', $tenantUuid)
            ->with('permissions')
            ->find($id);

        if (! $role) {
            return JsonResponser::send(true, 'Role not found.', null, 200);
        }

        $permissions = $role->permissions->map(function ($perm) {
            return [
                'id' => $perm->id,
                'name' => $perm->name,
            ];
        })->values();

        $userIds = DB::connection('tenant')
            ->table('role_user')
            ->where('role_id', $role->id)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            $roleData = [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'status' => $role->status,
                'permissions' => $permissions,
                'users' => [],
                'user_count' => 0,
                'created_at' => $role->created_at?->toDateTimeString(),
                'updated_at' => $role->updated_at?->toDateTimeString(),
            ];

            return JsonResponser::send(false, 'Role retrieved successfully.', $roleData, 200);
        }

        $users = \App\Models\User::query()
            ->whereIn('id', $userIds)
            ->whereHas('tenantUsers', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->whereNull('deleted_at');
            })
            ->with(['tenantUsers' => function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->whereNull('deleted_at');
            }])
            ->get();

        $usersData = $users->map(function ($user) use ($role) {

            $userRole = [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ];

            return [
                'id' => $user->id,
                'name' => $user->fullname ?? trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'status' => $user->status ?? ($user->tenantUsers->first()->status ?? null),
                'joined_at' => $user->created_at?->toDateTimeString(),
                'userRole' => $userRole,
            ];
        })->values();

        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'status' => $role->status,
            'permissions' => $permissions,
            'users' => $usersData,
            'user_count' => $usersData->count(),
            'created_at' => $role->created_at?->toDateTimeString(),
            'updated_at' => $role->updated_at?->toDateTimeString(),
        ];

        return JsonResponser::send(false, 'Role retrieved successfully.', $roleData, 200);
    }


    /**
     * Update an existing role and reassign permissions.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $role = Role::findOrFail($id);
            // Update role
            // $role->update([
            //     'name'         => $request->name,
            //     'display_name' => $request->display_name,
            //     'description'  => $request->description,
            //     'status'       => $request->status,
            // ]);

            // Sync permissions if provided
            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            // Commit tenant transaction
            DB::connection('tenant')->commit();

            $dataToLog = [
                'causer_id'       => $currentUser->id,
                'action_id'       => $role->id,
                'action'          => 'Update',
                'action_type'     => "Models\Role",
                'log_name'        => "Role updated successfully",
                'description'     => "{$currentUser->first_name} {$currentUser->last_name} updated the role: {$role->name}",
                'module_accessed' => ListModuleEnums::Records
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            return JsonResponser::send(false, 'Role updated successfully with permissions.', $role, 200);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Failed to update role. Please try again.', $e->getMessage(), 500);
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
