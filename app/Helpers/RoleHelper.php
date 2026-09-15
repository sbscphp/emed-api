<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class RoleHelper
{
    /**
     * The consultant id to scope list queries by, or null when the current user
     * should NOT be scoped.
     *
     * Consultants only see patients/visits assigned to them (patient_visits.doctor_id).
     * Admins/super-admins (even if they also hold the consultant role) and every other
     * role return null = unrestricted.
     */
    public static function consultantScopeId(?string $tenantUuid): ?int
    {
        $user = Auth::user();
        if (!$user || !$tenantUuid) {
            return null;
        }

        // Tenant-scoped role names (mirrors AuthenticationController::login).
        $roleNames = $user->roles()
            ->where('roles.tenant_id', $tenantUuid)
            ->pluck('roles.name');

        if ($roleNames->contains('admin') || $roleNames->contains('super_admin')) {
            return null;
        }

        return $roleNames->contains('consultant') ? (int) $user->id : null;
    }
}
