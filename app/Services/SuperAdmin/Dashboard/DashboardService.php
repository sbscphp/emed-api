<?php

namespace App\Services\SuperAdmin\Dashboard;

use App\Enums\GeneralEnums;
use App\Models\ClientUsageCharge;
use App\Models\LandlordAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function dashboard($request): array
    {
        return [
            'stats'
            => $this->getStats(),
            'platform_health'         => $this->getPlatformHealth(),
            // 'visit_distribution'      => $this->getVisitDistribution(),
            'top_hospitals'           => $this->getTopHospitals(),
            'recent_activity'         => $this->getRecentActivity($request),
        ];
    }

    /**
     * Get dashboard statistics
     * Returns total hospitals, active hospitals, total visits, and annual license revenue
     */
    private function getStats(): array
    {
        $totalHospitals = Tenant::count();
        $activeHospitals = Tenant::where('status', GeneralEnums::ACTIVE->value)->count();
        $totalVisits = $this->getTotalVisits();
        $annualRevenue = $this->calculateAnnualRevenue();

        return [
            'total_hospitals'         => $totalHospitals,
            'active_hospitals'        => $activeHospitals,
            'total_visits'            => $totalVisits,
            'annual_license_revenue'  => $annualRevenue,
        ];
    }

    /**
     * Calculate total visits across all tenants
     */
    private function getTotalVisits(): int
    {
        $totalVisits = 0;

        // Get all active tenants
        $tenants = Tenant::where('status', GeneralEnums::ACTIVE->value)->get();

        foreach ($tenants as $tenant) {
            // Count visits from client usage charges (landlord database)
            try {
                $tenantVisits = ClientUsageCharge::query()
                    ->where('tenant_id', $tenant->id)
                    ->sum('total_visits');

                $totalVisits += $tenantVisits ?? 0;
            } catch (\Exception $e) {
                // Skip if error occurs
                continue;
            }
        }

        return $totalVisits;
    }

    /**
     * Calculate annual license revenue from subscriptions
     */
    private function calculateAnnualRevenue(): float
    {
        return (float) Subscription::whereYear('license_start_date', '>=', now()->subYear()->year)
            ->where('status', GeneralEnums::ACTIVE->value)
            ->sum('license_fee');
    }

    /**
     * Get visit distribution by region (based on tenant country)
     */
    private function getVisitDistribution(): array
    {
        $totalHospitals = Tenant::count();
        $activeUsers = User::whereHas('superAdminRoles')->count();

        // Group tenants by country and calculate percentages
        $regionData = Tenant::query()
            ->whereNotNull('country')
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as count')
            ->get()
            ->map(function ($item) use ($totalHospitals) {
                return [
                    'name'       => $item->country ?? 'Unknown',
                    'percentage' => $totalHospitals > 0 ? round(($item->count / $totalHospitals) * 100) : 0,
                ];
            })
            ->sortByDesc('percentage')
            ->values()
            ->toArray();

        return [
            'active_users' => $activeUsers,
            'regions'      => $regionData,
        ];
    }

    /**
     * Get platform health metrics
     */
    private function getPlatformHealth(): array
    {
        $totalHospitals = Tenant::count();
        $totalVisits = $this->getTotalVisits();
        $averageVisitPerHospital = $totalHospitals > 0 ? round($totalVisits / $totalHospitals) : 0;

        // Monthly active users (tenant hospitals with usage activity this month)
        $startOfMonth = now()->startOfMonth();
        $monthlyActiveUsers = ClientUsageCharge::query()
            ->where('created_at', '>=', $startOfMonth)
            ->distinct('tenant_id')
            ->count('tenant_id');

        // Departments active - count of unique usage fees with activity this month
        $departmentsActive = 0;

        // Hospitals near expiry (subscriptions ending within 30 days from now)
        $hospitalsNearExpiry = Subscription::query()
            ->whereDate('license_end_date', '<=', now()->addDays(30))
            ->whereDate('license_end_date', '>=', now())
            ->count();

        return [
            'average_visit_per_hospital' => $averageVisitPerHospital,
            'monthly_active_users'       => $monthlyActiveUsers,
            'departments_active'         => $departmentsActive,
            'hospitals_near_expiry'      => $hospitalsNearExpiry,
        ];
    }

    /**
     * Get top hospitals by visit volume (last 30 days)
     */
    private function getTopHospitals(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $topHospitals = ClientUsageCharge::query()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->with('tenant')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(total_visits) as total_visits')
            ->orderByDesc('total_visits')
            ->limit(5)
            ->get()
            ->map(function ($charge) {
                // Get the maximum visits in a single record for percentage calculation
                $maxVisits = ClientUsageCharge::query()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->max('total_visits');

                $percentage = $maxVisits > 0 ? round(($charge->total_visits / $maxVisits) * 100) : 0;

                return [
                    'name'       => $charge->tenant->name ?? 'Unknown',
                    'percentage' => min($percentage, 100),
                    'visits'     => $charge->total_visits,
                ];
            })
            ->toArray();

        return $topHospitals;
    }

    /**
     * Get recent activity logs
     */
    private function getRecentActivity($request)
    {
        $query = LandlordAuditLog::query()
            ->with(['causer'])
            ->when($request->search_date, function ($query) use ($request) {
                $query->whereDate('created_at', $request->search_date);
            })
            ->when($request->search_param, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('action', 'like', "%{$request->search_param}%")
                        ->orWhere('description', 'like', "%{$request->search_param}%")
                        ->orWhereHas('causer', function ($subQ) use ($request) {
                            $subQ->where('name', 'like', "%{$request->search_param}%");
                        });
                });
            })
            ->when($request->action_module, function ($query) use ($request) {
                $query->where('action_module', $request->action_module);
            })
            ->latest('created_at');

        $paginate = $request->paginate ?? true;
        $limit = $request->limit ?? 10;

        if ($paginate) {
            return $query->paginate($limit)->through(function ($item) {
                return $this->formatActivity($item);
            });
        }

        return [
            'records' => $query->limit($limit)->get()->map(function ($item) {
                return $this->formatActivity($item);
            })->toArray(),
        ];
    }

    /**
     * Format activity log for response
     */
    private function formatActivity($activity): array
    {
        return [
            'id'        => $activity->id,
            'timestamp' => $activity->created_at->format('M d, Y h:i A'),
            'actor'     => $activity->causer?->name ?? 'System',
            'action'    => $activity->action,
            'description' => $activity->description,
            'module'    => $activity->action_module,
        ];
    }
}
