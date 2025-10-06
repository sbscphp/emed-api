<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PermissionAccessTypeEnum;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermissionAccessService
{
    /**
     * Determine the access type and remarks for a given permission.
     *
     * @param User $user The authenticated user.
     * @param Permission $permission The permission being checked.
     * @param Tenant $tenant The current tenant (tenant).
     * @return array Contains 'access_type' (full, partial, none) and 'remarks' (string or null).
     */
    public function getAccessDetails(User $user, Permission $permission, Tenant $tenant): array
    {
        $accessType = PermissionAccessTypeEnum::NONE->value;
        $remarks = [];

        // --- Step 1: Check if the user has the permission via Laratrust ---
        // This checks if the user has the permission directly or through their assigned roles
        $userHasPermission = $this->accessTo($user, $permission->name);

        if (!$userHasPermission) {
            // If the user does not have the permission, access is 'none' regardless of other factors
            return [
                'access_type' => PermissionAccessTypeEnum::NONE->value,
                'remarks' => null,
            ];
        }

        // If the user has the permission, we assume 'full' access initially
        // and then downgrade to 'partial' if any restrictions apply.
        $accessType = PermissionAccessTypeEnum::FULL->value;

        // --- Step 2: Check Company Onboarding Status ---
        // Assuming 'approved' is the status that grants full access to features.
        // if ($tenant->tenantInformation->onboarding_status !== GeneralEnums::APPROVED->value) {
        //     $accessType = PermissionAccessTypeEnum::PARTIAL->value;
        //     $remarks[] = 'Company onboarding pending approval.';
        // }

        // --- Step 3: Check Company Subscription Status and Features ---
        // Assuming a `hasOne` relationship from Company to Subscription.
        // And `plan_features` is a JSON column on the `subscriptions` table,
        // containing an 'allowed_permissions' array.
        // $subscription = $tenant?->subscriber?->subscriptionHistories;

        // if (empty($subscription)) {
        //     // No subscription found for the company
        //     $accessType = PermissionAccessTypeEnum::PARTIAL->value;
        //     $remarks[] = 'No active subscription found for the company.';
        // } elseif (
        //     !$subscription
        //         ->where('subscribed_at', '<=', now())
        //         ->where('end_date', '>=', now())
        //         ->first()
        // ) {
        //     // Subscription is not active (e.g., expired or cancelled)
        //     $accessType = PermissionAccessTypeEnum::PARTIAL->value;
        //     $remarks[] = 'Company subscription has expired or is inactive.';
        // } else {
        //     $activeSubscription = $subscription
        //         ->where('subscribed_at', '<=', now())
        //         ->where('end_date', '>=', now())
        //         ->sortByDesc('created_at')
        //         ->first();

        //     if (
        //         !$activeSubscription
        //             ->subscriptionPlanLineItem
        //             ->moduleApps()
        //             ->whereRaw('module_apps.id = ?', [$permission->app_id])
        //             ->exists()
        //     ) {
        //         $accessType = PermissionAccessTypeEnum::PARTIAL->value;
        //         $remarks[] = 'Feature not included in your current subscription plan.';
        //     }
        // }

        // Combine remarks into a single string if partial access, otherwise null
        $finalRemarks = $remarks ? implode(', ', $remarks) : null;

        return [
            'access_type' => $accessType,
            'remarks' => $finalRemarks,
        ];
    }

    public function allPermissions()
    {
        $permissions = Permission::all();

        // Map over each permission to determine its access details.
        $formattedPermissions = $permissions->map(function ($permission) {
            // Use the service to get access type and remarks for each permission.
            $user = Auth::user();
            $tenant = $user->getCurrentTenant();
            $accessDetails = $this->getAccessDetails($user, $permission, $tenant);

            return [
                'name' => $permission->name,
                'module' => $permission->module,
                'sub_module' => $permission->sub_module,
                'access_type' => $accessDetails['access_type'],
                'remarks' => $accessDetails['remarks'],
            ];
        });

        return $formattedPermissions;
    }

    public function applicationAccess()
    {
        $user = Auth::user();

        return [
            // --- Dashboard ---
            [
                'name' => 'Dashboard',
                'tag'  => 'dashboard.management',
                'access' => $this->accessTo($user, 'Dashboard.management.view'),
            ],

            // --- Record Management ---
            [
                'name' => 'Record (Create)',
                'tag'  => 'record.management.create',
                'access' => $this->accessTo($user, 'Record.management.create'),
            ],
            [
                'name' => 'Record (View)',
                'tag'  => 'record.management.view',
                'access' => $this->accessTo($user, 'Record.management.view'),
            ],
            [
                'name' => 'Record (Modify)',
                'tag'  => 'record.management.modify',
                'access' => $this->accessTo($user, 'Record.management.modify'),
            ],

            // --- Nurse Management ---
            [
                'name' => 'Nurse (Create)',
                'tag'  => 'nurse.management.create',
                'access' => $this->accessTo($user, 'Nurse.management.create'),
            ],
            [
                'name' => 'Nurse (View)',
                'tag'  => 'nurse.management.view',
                'access' => $this->accessTo($user, 'Nurse.management.view'),
            ],
            [
                'name' => 'Nurse (Modify)',
                'tag'  => 'nurse.management.modify',
                'access' => $this->accessTo($user, 'Nurse.management.modify'),
            ],

            // --- Consultant Management ---
            [
                'name' => 'Consultant (Create)',
                'tag'  => 'consultant.management.create',
                'access' => $this->accessTo($user, 'Consultant.management.create'),
            ],
            [
                'name' => 'Consultant (View)',
                'tag'  => 'consultant.management.view',
                'access' => $this->accessTo($user, 'Consultant.management.view'),
            ],
            [
                'name' => 'Consultant (Modify)',
                'tag'  => 'consultant.management.modify',
                'access' => $this->accessTo($user, 'Consultant.management.modify'),
            ],

            // --- Pharmacy Management ---
            [
                'name' => 'Pharmacy (Create)',
                'tag'  => 'pharmacy.management.create',
                'access' => $this->accessTo($user, 'Pharmacy.management.create'),
            ],
            [
                'name' => 'Pharmacy (View)',
                'tag'  => 'pharmacy.management.view',
                'access' => $this->accessTo($user, 'Pharmacy.management.view'),
            ],
            [
                'name' => 'Pharmacy (Modify)',
                'tag'  => 'pharmacy.management.modify',
                'access' => $this->accessTo($user, 'Pharmacy.management.modify'),
            ],

            // --- Laboratory Management ---
            [
                'name' => 'Laboratory (Create)',
                'tag'  => 'laboratory.management.create',
                'access' => $this->accessTo($user, 'Laboratory.management.create'),
            ],
            [
                'name' => 'Laboratory (View)',
                'tag'  => 'laboratory.management.view',
                'access' => $this->accessTo($user, 'Laboratory.management.view'),
            ],
            [
                'name' => 'Laboratory (Modify)',
                'tag'  => 'laboratory.management.modify',
                'access' => $this->accessTo($user, 'Laboratory.management.modify'),
            ],

            // --- Radiology Management ---
            [
                'name' => 'Radiology (Create)',
                'tag'  => 'radiology.management.create',
                'access' => $this->accessTo($user, 'Radiology.management.create'),
            ],
            [
                'name' => 'Radiology (View)',
                'tag'  => 'radiology.management.view',
                'access' => $this->accessTo($user, 'Radiology.management.view'),
            ],
            [
                'name' => 'Radiology (Modify)',
                'tag'  => 'radiology.management.modify',
                'access' => $this->accessTo($user, 'Radiology.management.modify'),
            ],

            // --- Billing Management ---
            [
                'name' => 'Billing (Create)',
                'tag'  => 'billing.management.create',
                'access' => $this->accessTo($user, 'Billing.management.create'),
            ],
            [
                'name' => 'Billing (View)',
                'tag'  => 'billing.management.view',
                'access' => $this->accessTo($user, 'Billing.management.view'),
            ],
            [
                'name' => 'Billing (Modify)',
                'tag'  => 'billing.management.modify',
                'access' => $this->accessTo($user, 'Billing.management.modify'),
            ],

            // --- Logs & Reports ---
            [
                'name' => 'Logs (View)',
                'tag'  => 'logs.management.view',
                'access' => $this->accessTo($user, 'Logs.management.view'),
            ],
            [
                'name' => 'Reports (View)',
                'tag'  => 'reports.management.view',
                'access' => $this->accessTo($user, 'Reports.management.view'),
            ],

            // --- User Management ---
            [
                'name' => 'User (Create)',
                'tag'  => 'user.management.create',
                'access' => $this->accessTo($user, 'User.management.create'),
            ],
        ];
    }

    public function accessTo(User $user, string $permissions): bool
    {
        return DB::connection('tenant')
            ->table('permissions')
            ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('permissions.name', $permissions)
            ->exists();
    }

    // public function getModulePermissions()
    // {
    //     $permissions = Permission::all();

    //     $modules = []; // Initialize an array to hold grouped modules and permissions

    //     foreach ($permissions as $permission) {
    //         // Determine module and sub-module names, providing defaults if null.
    //         $moduleName = $permission->module ?? 'Uncategorized';
    //         $subModuleName = $permission->sub_module ?? 'General';

    //         // Initialize module structure if it doesn't exist
    //         if (!isset($modules[$moduleName])) {
    //             $modules[$moduleName] = [
    //                 'name' => $moduleName,
    //                 'sub_modules' => [], // Sub-modules will be stored as an associative array initially
    //             ];
    //         }

    //         // Initialize sub-module structure if it doesn't exist
    //         if (!isset($modules[$moduleName]['sub_modules'][$subModuleName])) {
    //             $modules[$moduleName]['sub_modules'][$subModuleName] = [
    //                 'name' => $subModuleName,
    //                 'permissions' => [], // Permissions within this sub-module
    //             ];
    //         }

    //         // Get access details for the current permission

    //         $user = Auth::user();
    //         $tenant = $user->activeMerchant->tenant;
    //         $accessDetails = $this->getAccessDetails($user, $permission, $tenant);

    //         // Add the permission details to the appropriate sub-module
    //         $modules[$moduleName]['sub_modules'][$subModuleName]['permissions'][] = [
    //             'name' => $permission->name,
    //             'access_type' => $accessDetails['access_type'],
    //             'remarks' => $accessDetails['remarks'],
    //         ];
    //     }

    //     // Convert the associative arrays for 'modules' and 'sub_modules' into indexed arrays
    //     // for cleaner JSON output.
    //     return collect($modules)->map(function ($module) {
    //         $module['sub_modules'] = collect($module['sub_modules'])->values()->all();
    //         return $module;
    //     })->values()->all();
    // }

    // public function applicationRequests($module)
    // {
    //     $currentUser = Auth::user();
    //     $currentTenant = Tenant::current();
    //     $admins = $currentTenant->getTenantAdmins();
    //     $admins->each->notify(new ApplicationRequestNotification($module, $currentUser));
    // }
}
