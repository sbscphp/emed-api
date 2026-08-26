<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::current();

        if (!$tenant) {
            return;
        }

        $apps = Config::get('permissions.apps');

        foreach ($apps as $appName => $modules) {
            foreach ($modules as $moduleName => $actions) {
                foreach ($actions as $action) {
                    $name = "{$appName}.{$moduleName}.{$action}";

                    Permission::updateOrCreate(
                        ['name' => $name],
                        [
                            'module'       => $appName,
                            'sub_module'   => $moduleName,
                            'display_name' => ucfirst($action) . ' ' . $appName,
                            'description'  => "Allows user to {$action} in {$appName} {$moduleName}",
                        ]
                    );
                }
            }
        }
        // else {
        //     $superAdminModules = Config::get('permissions.super_admin');

        //     foreach ($superAdminModules as $moduleName => $subModulesOrActions) {
        //         foreach ($subModulesOrActions as $subModuleName => $actions) {
        //             if (is_array($actions)) {
        //                 foreach ($actions as $action) {
        //                     $name = "{$moduleName}.{$subModuleName}.{$action}";
        //                     SuperAdminPermission::updateOrCreate(
        //                         ['name' => $name],
        //                         [
        //                             'module' => $moduleName,
        //                             'sub_module' => $subModuleName,
        //                             'display_name' => null,
        //                             'description' => null
        //                         ]
        //                     );
        //                 }
        //             } else {
        //                 $name = "{$moduleName}.{$actions}";
        //                 SuperAdminPermission::updateOrCreate(
        //                     ['name' => $name],
        //                     [
        //                         'module' => $moduleName,
        //                         'sub_module' => null,
        //                         'display_name' => null,
        //                         'description' => null
        //                     ]
        //                 );
        //             }
        //         }
        //     }
        // }
    }
}
