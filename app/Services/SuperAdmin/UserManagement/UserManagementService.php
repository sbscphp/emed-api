<?php

namespace App\Services\SuperAdmin\UserManagement;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Mail\UserDefaultPasswordMail;
use App\Models\SuperAdminRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserManagementService
{
    // ── Users ──

    public function usersOverview($request)
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = User::query()
            ->whereHas('superAdminRoles')
            ->with('superAdminRoles:id,name,display_name')
            ->when($request->search_param, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('first_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('email', 'LIKE', '%' . $request->search_param . '%');
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when(!empty($request->start_date) && !empty($request->end_date), function ($query) use ($request) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay()
                ]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFilter[0])->startOfDay(),
                    Carbon::parse($dateFilter[1])->endOfDay()
                ]);
            })
            ->when($request->sortBy == 'alphabetically', function ($query) {
                $query->orderBy('firstname', 'ASC');
            })
            ->orderBy('id', 'DESC');

        if ($request->paginate && !$request->export) {
            return $records->paginate($request->limit ?? 15);
        }

        return $records->get();
    }

    public function userStats($request): array
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = User::query()
            ->whereHas('superAdminRoles')
            ->when($dateFilter, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFilter[0])->startOfDay(),
                    Carbon::parse($dateFilter[1])->endOfDay()
                ]);
            });

        return [
            'total'    => (clone $records)->count(),
            'active'   => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(),
            'inactive' => (clone $records)->where('status', GeneralEnums::INACTIVE->value)->count(),
        ];
    }

    public function createUser($request)
    {
        $name     = trim($request->input('name'));
        $parts    = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName  = $parts[1] ?? null;

        $roleId = $request->input('role_id');
        $role   = SuperAdminRole::findOrFail($roleId);

        $user = User::create([
            'uuid'         => Str::uuid()->toString(),
            'fullname'     => $name,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $request->input('email'),
            'phone_number' => $request->input('phone_number'),
            'status'       => $request->input('status', GeneralEnums::ACTIVE->value),
            'password'     => Hash::make('password'), // Default password
            'is_verified'  => false,
            'is_completed' => false,
            'can_login'    => true,
        ]);

        // Attach the selected super admin role
        $user->superAdminRoles()->attach($roleId);

        $data = [
            'firstname' => $user->first_name,
            'lastname' => $user->last_name,
            'email' => $user->email,
            'default_password' => 'password',
        ];

        Mail::to($user->email)->send(new UserDefaultPasswordMail($data));

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\User',
            'action_module' => 'Super Admin Users',
            'action_id' => $user->id,
            'action' => 'Create',
            'log_name' => 'Create Super Admin User',
            'description' => sprintf('Created super admin user %s (%s).', $user->fullname, $user->email),
            'module_accessed' => 'Super Admin User Management',
        ]);

        return $user->load('superAdminRoles:id,name,display_name');
    }

    public function showUser($id)
    {
        return User::where('id', $id)
            ->whereHas('superAdminRoles')
            ->with('superAdminRoles:id,name,display_name')
            ->first();
    }

    public function updateUser($id, $request)
    {
        $user = User::where('id', $id)
            ->whereHas('superAdminRoles')
            ->first();

        if (!$user) {
            throw new \Exception('User not found.');
        }

        $name      = trim($request->input('name'));
        $parts     = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName  = $parts[1] ?? null;

        $oldData = $user->only(['fullname', 'first_name', 'last_name', 'email', 'phone_number', 'status']);

        $user->update([
            'fullname'     => $name,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $request->input('email'),
            'phone_number' => $request->input('phone_number'),
            'status'       => $request->input('status'),
        ]);

        // Sync the role (replace existing super admin roles with new one)
        $roleId = $request->input('role_id');
        SuperAdminRole::findOrFail($roleId);
        $user->superAdminRoles()->sync([$roleId]);

        $newData = $user->fresh()->only(['fullname', 'first_name', 'last_name', 'email', 'phone_number', 'status']);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\User',
            'action_module' => 'Super Admin Users',
            'action_id' => $user->id,
            'action' => 'Update',
            'log_name' => 'Update Super Admin User',
            'description' => sprintf('Updated super admin user %s (%s).', $user->fullname, $user->email),
            'module_accessed' => 'Super Admin User Management',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);

        return $user->fresh()->load('superAdminRoles:id,name,display_name');
    }

    public function toggleUserStatus($id)
    {
        $user = User::where('id', $id)
            ->whereHas('superAdminRoles')
            ->first();
        if (!$user) {
            throw new \Exception('User not found.');
        }

        $user->status = $user->status == GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;

        $previousStatus = $user->status;
        $user->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\User',
            'action_module' => 'Super Admin Users',
            'action_id' => $user->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Super Admin User Status',
            'description' => sprintf('Changed super admin user %s status from %s to %s.', $user->fullname, $previousStatus, $user->status),
            'module_accessed' => 'Super Admin User Management',
            'old_data' => ['status' => $previousStatus],
            'new_data' => ['status' => $user->status],
        ]);

        return $user;
    }

    public function removeUser($id)
    {
        $user = User::where('id', $id)
            ->whereHas('superAdminRoles')
            ->first();

        if (!$user) {
            throw new \Exception('User not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\User',
            'action_module' => 'Super Admin Users',
            'action_id' => $user->id,
            'action' => 'Delete',
            'log_name' => 'Remove Super Admin User',
            'description' => sprintf('Removed super admin user %s (%s).', $user->fullname, $user->email),
            'module_accessed' => 'Super Admin User Management',
        ]);

        // Detach from all super admin roles
        $user->superAdminRoles()->detach();
        $user->delete();

        return $user;
    }

    // ── Roles ──

    public function rolesOverview($request)
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = SuperAdminRole::query()
            ->withCount('users')
            ->when($request->search_param, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('display_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('name', 'LIKE', '%' . $request->search_param . '%');
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFilter[0])->startOfDay(),
                    Carbon::parse($dateFilter[1])->endOfDay()
                ]);
            })
            ->orderBy('id', 'DESC');

        if ($request->paginate && !$request->export) {
            return $records->paginate($request->limit ?? 15);
        }

        return $records->get();
    }

    public function createRole($request)
    {
        $slug = Str::slug($request->input('name'));
        $displayName = Str::title($request->input('name'));

        $role = SuperAdminRole::where('name', $slug)->first();

        if ($role) {
            throw new \Exception('Role already exists.');
        }

        $role = SuperAdminRole::create([
            'name'         => $slug,
            'display_name' => $displayName,
            'description'  => $request->input('description'),
            'status'       => 'Active',
        ]);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\SuperAdminRole',
            'action_module' => 'Super Admin Roles',
            'action_id' => $role->id,
            'action' => 'Create',
            'log_name' => 'Create Super Admin Role',
            'description' => sprintf('Created super admin role %s.', $role->display_name),
            'module_accessed' => 'Super Admin User Management',
        ]);

        return $role;
    }

    public function showRole($id)
    {
        return SuperAdminRole::where('id', $id)
            ->with('users:id,first_name,last_name,email,status,profile_picture')
            ->withCount('users')
            ->firstOrFail();
    }

    public function updateRole($id, $request)
    {
        $role = SuperAdminRole::find($id);

        if (!$role) {
            throw new \Exception('Role not found.');
        }

        $oldData = $role->only(['name', 'display_name', 'description']);

        $role->update([
            'name'         => Str::slug($request->input('name')),
            'display_name' => Str::title($request->input('name')),
            'description'  => $request->input('description'),
        ]);

        $newData = $role->fresh()->only(['name', 'display_name', 'description']);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\SuperAdminRole',
            'action_module' => 'Super Admin Roles',
            'action_id' => $role->id,
            'action' => 'Update',
            'log_name' => 'Update Super Admin Role',
            'description' => sprintf('Updated super admin role %s.', $role->display_name),
            'module_accessed' => 'Super Admin User Management',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);

        return $role->fresh();
    }

    public function toggleRoleStatus($id)
    {
        $role = SuperAdminRole::findOrFail($id);

        $role->status = $role->status == GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;

        $previousStatus = $role->status;
        $role->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\SuperAdminRole',
            'action_module' => 'Super Admin Roles',
            'action_id' => $role->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Super Admin Role Status',
            'description' => sprintf('Changed super admin role %s status from %s to %s.', $role->display_name, $previousStatus, $role->status),
            'module_accessed' => 'Super Admin User Management',
            'old_data' => ['status' => $previousStatus],
            'new_data' => ['status' => $role->status],
        ]);

        return $role;
    }

    public function deleteRole($id)
    {
        $role = SuperAdminRole::find($id);
        if (!$role) {
            throw new \Exception('Role not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\SuperAdminRole',
            'action_module' => 'Super Admin Roles',
            'action_id' => $role->id,
            'action' => 'Delete',
            'log_name' => 'Delete Super Admin Role',
            'description' => sprintf('Deleted super admin role %s.', $role->display_name),
            'module_accessed' => 'Super Admin User Management',
        ]);

        $role->delete();
    }
}
