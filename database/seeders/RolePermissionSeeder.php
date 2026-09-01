<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Marks the role that holds every module rather than a named list.
     */
    protected const ALL_MODULES = '*';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (!$tenant) {
            $this->command?->warn('Skipping RolePermissionSeeder: no tenant context found.');
            return;
        }

        // $mapPermission = collect(config('role_permission_seeder.permissions_map'));
        // $config = config('role_permission_seeder.roles_structure');
        // $mapPermission = collect(config('role_permission_seeder.permissions_map'));

        // Each role, with the config('permissions.apps') modules it owns. Most
        // roles own the single module named after them; record and nurse each
        // carry a second one, and admin is marked self::ALL_MODULES because it
        // holds every module there is.
        //
        // Every role spells its modules out rather than leaning on the fallback
        // below, so what a role can reach is readable here and nowhere else.
        $config = [
            'admin' => [
                'description' => 'This is the administrator role. It has full access to everything including global settings. This role is not editable.',
                'modules' => self::ALL_MODULES,
            ],
            'record' => [
                'description' => 'This role can access all the record modules of the software and have all the privileges within the system.',
                // Appointments are booked from the records desk.
                'modules' => ['record', 'appointment'],
            ],
            'nurse' => [
                'description' => 'This role can access all the nurse modules of the software and have all the privileges within the system.',
                // Admissions are run from the ward.
                'modules' => ['nurse', 'admission'],
            ],
            'consultant' => [
                'description' => 'This role can access all the consultant modules of the software and have all the privileges within the system.',
                'modules' => ['consultant'],
            ],
            'pharmacy' => [
                'description' => 'This role can access all the pharmacy modules of the software and have all the privileges within the system.',
                'modules' => ['pharmacy'],
            ],
            'laboratory' => [
                'description' => 'This role can access all the laboratory modules of the software and have all the privileges within the system.',
                'modules' => ['laboratory'],
            ],
            'radiology' => [
                'description' => 'This role can access all the radiology modules of the software and have all the privileges within the system.',
                'modules' => ['radiology'],
            ],
            'billing' => [
                'description' => 'This role can access all the billing modules of the software and have all the privileges within the system.',
                'modules' => ['billing'],
            ],
        ];

        foreach ($config as $key => $definition) {
            $role = Role::updateOrCreate(
                [
                    'tenant_id' => $tenant->uuid,
                    'name'      => $key,
                ],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $key)),
                    'description'  => $definition['description'],
                    'status'       => 'Active'
                ]
            );

            // Falls back to the module named after the role, so a role added
            // later without a modules key still gets its own permissions.
            $modules = $definition['modules'] ?? [$key];

            $permissionIds = $modules === self::ALL_MODULES
                ? Permission::pluck('id')->all()
                : Permission::whereIn('module', $modules)->pluck('id')->all();

            if (!empty($permissionIds)) {
                $role->permissions()->syncWithoutDetaching($permissionIds);

                // The gates in AuthServiceProvider read permission_user, not
                // permission_role, so a module added to a role after its users
                // were created never reaches them until they are topped up here.
                $this->grantToRoleHolders($role, $permissionIds);
            }

            // $role = Role::where('tenant_id', $tenant->uuid)->where('name', $key)->first();
            // if (!$role) {
            //     $role = DB::connection('tenant')->table('roles')->insert([
            //         'tenant_id'    => $tenant->uuid,
            //         'name'         => $key,
            //         'display_name' => ucwords(str_replace('_', ' ', $key)),
            //         'description'  => $description,
            //         'status'       => 'Active',
            //         'created_at'   => now(),
            //         'updated_at'   => now(),
            //     ]);
            // }

            // $permissions = Permission::where('module', $key)->get();
            // $role->givePermissions($permissions->pluck('id')->toArray());

            $this->command->info("Created role: {$key}");

            // if ($key == 'super_admin') {
            //     $description = 'Super admin has full access to everything including global settings.';
            // } else if ($key == 'admin') {
            //     $description = 'Admin can access all the modules of the software and have all the privileges within the system.';
            // } else if ($key == 'nurse') {
            //     $description = 'Nurse can access all the nurse modules of the software and have all the privileges within the system.';
            // } else if ($key == 'billing') {
            //     $description = 'Billing user role can access all the billing modules of the software and have all the privileges within the system.';
            // } else if ($key == 'consultant') {
            //     $description = 'Consultant user role can access all the consultant modules of the software and have all the privileges within the system.';
            // } else if ($key == 'pharmacy') {
            //     $description = 'Pharmacy user role can access all the pharmacy modules of the software and have all the privileges within the system.';
            // } else if ($key == 'billing') {
            //     $description = 'Billing user role can access all the billing modules of the software and have all the privileges within the system.';
            // } else if ($key == 'record') {
            //     $description = 'Record user role can access all the record modules of the software and have all the privileges within the system.';
            // } else {
            //     $description = 'All the privileges within the system has been imported.';
            // }

            // // Create a new role
            // $role = Role::firstOrCreate([
            //     'name' => $key,
            //     'display_name' => ucwords(str_replace('_', ' ', $key)),
            //     'description' => $description
            // ]);
            // $permissions = [];

            // $this->command->info('Creating Role ' . strtoupper($key));

            // // Reading role permission modules
            // foreach ($modules as $module => $value) {

            //     foreach (explode(',', $value) as $p => $perm) {

            //         $permissionValue = $mapPermission->get($perm);

            //         $permissions[] = Permission::firstOrCreate([
            //             'name' => $module . '-' . $permissionValue,
            //             'display_name' => ucfirst($permissionValue) . ' ' . ucfirst($module),
            //             'description' => ucfirst($permissionValue) . ' ' . ucfirst($module),
            //         ])->id;

            //         $this->command->info('Creating Permission to ' . $permissionValue . ' for ' . $module);
            //     }
            // }

            // // Attach all permissions to the role
            // $role->permissions()->sync($permissions);

            // if (Config::get('role_permission_seeder.create_users')) {
            //     $this->command->info("Creating '{$key}' user");
            //     // Create default user for each role
            //     $user = User::create([
            //         'name' => ucwords(str_replace('_', ' ', $key)),
            //         'email' => $key . '@app.com',
            //         'password' => bcrypt('password')
            //     ]);
            //     $user->addRole($role);
            // }
        }

        // $admin = User::first();
        // $superAdminRole = Role::where('name', 'super_admin')->first();
        // $admin->addRole($superAdminRole);

        // $admin->permissions()->sync($superAdminRole->permissions);
    }

    /**
     * Truncates all the laratrust tables and the users table
     *
     * @return  void
     */
    // public function truncateLaratrustTables()
    // {
    //     $this->command->info('Truncating User, Role and Permission tables');

    //     DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    //     DB::table('permission_role')->truncate();
    //     DB::table('permission_user')->truncate();
    //     DB::table('role_user')->truncate();

    //     if (Config::get('role_permission_seeder.truncate_tables')) {
    //         Role::query()->delete();
    //         Permission::query()->delete();
    //     }
    //     DB::statement('ALTER TABLE roles AUTO_INCREMENT = 1');
    //     DB::statement('ALTER TABLE permissions AUTO_INCREMENT = 1');

    //     if (Config::get('role_permission_seeder.truncate_tables') && Config::get('role_permission_seeder.create_users')) {
    //         User::query()->delete();
    //     }

    //     DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    // }

    /**
     * Push a role's permissions down onto the users who already hold it.
     *
     * Users are created with a copy of their role's permissions in
     * permission_user, so a module added to config('permissions.apps') later
     * lands in permissions and permission_role but leaves the existing users
     * behind. Topping them up here is what makes re-running the seeder enough.
     *
     * syncWithoutDetaching only adds, so a user whose permissions were tuned by
     * hand keeps what they were given.
     *
     * @param  \App\Models\Role  $role
     * @param  array<int, int>  $permissionIds
     * @return void
     */
    protected function grantToRoleHolders($role, array $permissionIds)
    {
        // Roles and the role_user pivot live on the tenant connection while
        // users live on the landlord one, so the pivot is read directly rather
        // than through a relation that would try to join across databases.
        $userIds = DB::connection('tenant')
            ->table('role_user')
            ->where('role_id', $role->id)
            ->pluck('user_id')
            ->all();

        if (empty($userIds)) {
            return;
        }

        User::whereIn('id', $userIds)->get()->each(function ($user) use ($permissionIds) {
            $user->permissions()->syncWithoutDetaching($permissionIds);
        });
    }
}
