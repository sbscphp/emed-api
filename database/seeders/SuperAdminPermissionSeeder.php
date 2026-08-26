<?php

namespace Database\Seeders;

use App\Models\SuperAdminPermission;
use Illuminate\Database\Seeder;

class SuperAdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'view-dashboard', 'display_name' => 'View Dashboard', 'module' => 'Dashboard', 'description' => 'Access the main dashboard'],

            // Subscription Management
            ['name' => 'view-subscriptions', 'display_name' => 'View Subscriptions', 'module' => 'Subscription Management', 'description' => 'View subscription plans and dashboard'],
            ['name' => 'create-subscription', 'display_name' => 'Create Subscription', 'module' => 'Subscription Management', 'description' => 'Create new subscription plans'],
            ['name' => 'edit-subscription', 'display_name' => 'Edit Subscription', 'module' => 'Subscription Management', 'description' => 'Edit subscription plans'],
            ['name' => 'delete-subscription', 'display_name' => 'Delete Subscription', 'module' => 'Subscription Management', 'description' => 'Delete subscription plans'],
            ['name' => 'view-subscribers', 'display_name' => 'View Subscribers', 'module' => 'Subscription Management', 'description' => 'View subscriber details'],
            ['name' => 'manage-refund-requests', 'display_name' => 'Manage Refund Requests', 'module' => 'Subscription Management', 'description' => 'Approve or reject refund requests'],
            ['name' => 'manage-modification-requests', 'display_name' => 'Manage Modification Requests', 'module' => 'Subscription Management', 'description' => 'Approve or reject modification requests'],

            // Client Management
            ['name' => 'view-clients', 'display_name' => 'View Clients', 'module' => 'Client Management', 'description' => 'View client details and dashboard'],
            ['name' => 'toggle-client-status', 'display_name' => 'Toggle Client Status', 'module' => 'Client Management', 'description' => 'Activate or deactivate clients'],

            // User Management
            ['name' => 'view-users', 'display_name' => 'View Users', 'module' => 'User Management', 'description' => 'View super admin users'],
            ['name' => 'add-users', 'display_name' => 'Add Users', 'module' => 'User Management', 'description' => 'Add users to super admin roles'],
            ['name' => 'remove-users', 'display_name' => 'Remove Users', 'module' => 'User Management', 'description' => 'Remove users from super admin'],
            ['name' => 'toggle-user-status', 'display_name' => 'Toggle User Status', 'module' => 'User Management', 'description' => 'Activate or deactivate users'],
            ['name' => 'view-roles', 'display_name' => 'View Roles', 'module' => 'User Management', 'description' => 'View super admin roles'],
            ['name' => 'create-role', 'display_name' => 'Create Role', 'module' => 'User Management', 'description' => 'Create new roles'],
            ['name' => 'edit-role', 'display_name' => 'Edit Role', 'module' => 'User Management', 'description' => 'Edit existing roles'],
            ['name' => 'delete-role', 'display_name' => 'Delete Role', 'module' => 'User Management', 'description' => 'Delete roles'],

            // Support
            ['name' => 'view-complaints', 'display_name' => 'View Complaints', 'module' => 'Support', 'description' => 'View complaints and support dashboard'],
            ['name' => 'resolve-complaints', 'display_name' => 'Resolve Complaints', 'module' => 'Support', 'description' => 'Resolve complaints'],
            ['name' => 'reply-complaints', 'display_name' => 'Reply Complaints', 'module' => 'Support', 'description' => 'Reply to complaints'],
        ];

        foreach ($permissions as $permission) {
            SuperAdminPermission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
