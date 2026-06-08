<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tenant = app('currentTenant');
        // $this->truncateLaratrustTables();

        // $mapPermission = collect(config('role_permission_seeder.permissions_map'));
        // $config = config('role_permission_seeder.roles_structure');
        // $mapPermission = collect(config('role_permission_seeder.permissions_map'));

        $config = [
            'admin' => 'This is the administrator role. It has full access to everything including global settings. This role is not editable.',
            'record' => 'This role can access all the record modules of the software and have all the privileges within the system.',
            'nurse' => 'This role can access all the nurse modules of the software and have all the privileges within the system.',
            'consultant' => 'This role can access all the consultant modules of the software and have all the privileges within the system.',
            'pharmacy' => 'This role can access all the pharmacy modules of the software and have all the privileges within the system.',
            'laboratory' => 'This role can access all the laboratory modules of the software and have all the privileges within the system.',
            'radiology' => 'This role can access all the radiology modules of the software and have all the privileges within the system.',
            'billing' => 'This role can access all the billing modules of the software and have all the privileges within the system.',
        ];

        foreach ($config as $key => $description) {
            // Create a new role
            $role = Role::firstOrCreate(
                [
                    'tenant_id' => $tenant->uuid,
                    'name'      => $key,
                ],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $key)),
                    'description'  => $description,
                    'status'       => 'Active'
                ]
            );

            $permissionIds = $key === 'admin'
                ? Permission::pluck('id')->all()
                : Permission::where('module', $key)->pluck('id')->all();
            if (!empty($permissionIds)) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
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

    public function truncateLaratrustTables()
    {
        $this->command->info('Truncating User, Role and Permission tables');

        Schema::disableForeignKeyConstraints();
        DB::table('permission_role')->truncate();
        DB::table('permission_user')->truncate();
        DB::table('role_user')->truncate();

        if (Config::get('role_permission_seeder.truncate_tables')) {
            Role::truncate();
            Permission::truncate();
        }

        if (Config::get('role_permission_seeder.truncate_tables') && Config::get('role_permission_seeder.create_users')) {
            User::truncate();
        }

        Schema::enableForeignKeyConstraints();
    }
}
