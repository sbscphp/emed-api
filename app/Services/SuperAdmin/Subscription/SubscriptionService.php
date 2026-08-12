<?php

namespace App\Services\SuperAdmin\Subscription;

use App\Enums\GeneralEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Exports\AuditLogExport;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class SubscriptionService
{
    /**
     * Get subscriber overview list
     */
    public function overview($request)
    {
        $period = $request->input('period');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $customDate = [];
        if ($period === 'custom date' && $startDateInput && $endDateInput) {
            $customDate = [$startDateInput, $endDateInput];
        }

        $dateFilter = GeneralHelper::dateFilter($period, $customDate);

        $query = Subscription::query()
            ->with('tenant', 'usageFee')
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->when($request->search_param, function ($query) use ($request) {
                $query->whereHas('tenant', function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('email', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('registration_number', 'LIKE', '%' . $request->search_param . '%');
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->usage_fee_id, function ($query) use ($request) {
                $query->where('usage_fee_id', $request->usage_fee_id);
            })
            ->when(($request->sort_by ?? null) === 'name_ascending', function ($query) {
                $query->join('landlord.tenants', 'subscriptions.tenant_id', '=', 'tenants.id')
                    ->orderBy('tenants.name', 'ASC');
            })
            ->when(($request->sort_by ?? null) === 'name_descending', function ($query) {
                $query->join('landlord.tenants', 'subscriptions.tenant_id', '=', 'tenants.id')
                    ->orderBy('tenants.name', 'DESC');
            });

        $paginate = $request->paginate ?? true;

        if ($paginate) {
            return $query->orderBy('subscriptions.id', 'DESC')->paginate($request->limit ?? 15);
        }

        return [
            'records' => $query->orderBy('subscriptions.id', 'DESC')->get(),
        ];
    }

    /**
     * Get list of subscription plans
     */
    public function getPlans($request)
    {
        $query = SubscriptionPlan::query()
            ->with('prices')
            ->when($request->search_param, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search_param . '%')
                    ->orWhere('description', 'LIKE', '%' . $request->search_param . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        return $query->orderBy('id', 'DESC')->get();
    }

    /**
     * Get subscription statistics
     */
    public function stats($request)
    {
        $period = $request->input('period');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $customDate = [];
        if ($period === 'custom date' && $startDateInput && $endDateInput) {
            $customDate = [$startDateInput, $endDateInput];
        }

        $dateFilter = GeneralHelper::dateFilter($period, $customDate);

        $query = Subscription::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            });

        $total = (clone $query)->count();
        $active = (clone $query)->where('status', GeneralEnums::ACTIVE->value)->count();
        $inactive = (clone $query)->where('status', GeneralEnums::INACTIVE->value)->count();
        $totalRevenue = (clone $query)->sum('license_fee');

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'totalRevenue' => $totalRevenue,
        ];
    }

    /**
     * Export subscriber overview
     */
    public function export($data, $exportType = 'excel')
    {
        $records = is_array($data) && isset($data['records']) ? $data['records'] : $data;

        $exportData = collect($records)->map(function ($record) {
            return [
                'Hospital Name'  => $record->tenant->name ?? 'N/A',
                'Country'        => $record->tenant->country ?? 'N/A',
                'License Fee'    => $record->license_fee,
                'Usage Fee'      => $record->usageFee->amount ?? 0,
                'Licence Period' => $record->license_start_date . ' - ' . $record->license_end_date,
            ];
        })->toArray();

        $headers = !empty($exportData) ? array_keys($exportData[0]) : [];

        return match (strtolower($exportType)) {
            'csv' => ExportHelper::streamCsv(
                $exportData,
                $headers,
                'subscribers.csv'
            ),
            'excel' => Excel::download(
                new AuditLogExport(collect($exportData), $headers),
                'subscribers.xlsx'
            ),
            'pdf' => Pdf::loadView('exports.patients', [
                'patients' => $exportData
            ])->download('subscribers.pdf'),

            default => throw new \Exception('Invalid export format.'),
        };
    }

    /**
     * Create a new Subscription Plan
     */
    public function create($request)
    {
        return DB::connection('landlord')->transaction(function () use ($request) {
            $plan = SubscriptionPlan::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'status' => $request->input('status', GeneralEnums::ACTIVE->value),
            ]);

            $cycles = $request->input('billing_cycles') ?? $request->input('prices') ?? $request->input('cycles', []);

            foreach ($cycles as $item) {
                $plan->prices()->create([
                    'billing_cycle' => $item['billing_cycle'],
                    'price' => $item['price'],
                ]);
            }

            GeneralHelper::storeLandlordAuditLog([
                'action_type' => 'Models\\SubscriptionPlan',
                'action_module' => 'Super Admin Subscriptions',
                'action_id' => $plan->id,
                'action' => 'Create',
                'log_name' => 'Create Subscription Plan',
                'description' => sprintf('Created subscription plan "%s" (ID: %s).', $plan->name, $plan->id),
                'module_accessed' => 'Super Admin Subscription Management',
            ]);

            return $plan->load('prices');
        });
    }

    /**
     * Show single subscriber subscription
     */
    public function show($id)
    {
        $subscription = Subscription::with('tenant', 'usageFee')->find($id);

        if (!$subscription) {
            throw new \Exception('Subscription not found.');
        }

        return $subscription;
    }

    /**
     * Show single subscription plan
     */
    public function showPlan($id)
    {
        $plan = SubscriptionPlan::with('prices')->find($id);

        if (!$plan) {
            throw new \Exception('Subscription plan not found.');
        }

        return $plan;
    }

    /**
     * Update an existing Subscription Plan
     */
    public function update($id, $request)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            throw new \Exception('Subscription plan not found.');
        }

        return DB::connection('landlord')->transaction(function () use ($plan, $request) {
            $oldData = $plan->load('prices')->toArray();

            if ($request->has('name')) {
                $plan->name = $request->input('name');
            }

            if ($request->has('description')) {
                $plan->description = $request->input('description');
            }

            if ($request->has('status')) {
                $plan->status = $request->input('status');
            }

            $plan->save();

            $cycles = $request->input('billing_cycles') ?? $request->input('prices') ?? $request->input('cycles');
            if (!is_null($cycles) && is_array($cycles)) {
                $plan->prices()->delete();
                foreach ($cycles as $item) {
                    $plan->prices()->create([
                        'billing_cycle' => $item['billing_cycle'],
                        'price' => $item['price'],
                    ]);
                }
            }

            $newData = $plan->fresh()->load('prices')->toArray();

            GeneralHelper::storeLandlordAuditLog([
                'action_type' => 'Models\\SubscriptionPlan',
                'action_module' => 'Super Admin Subscriptions',
                'action_id' => $plan->id,
                'action' => 'Update',
                'log_name' => 'Update Subscription Plan',
                'description' => sprintf('Updated subscription plan "%s" (ID: %s).', $plan->name, $plan->id),
                'module_accessed' => 'Super Admin Subscription Management',
                'old_data' => $oldData,
                'new_data' => $newData,
            ]);

            return $plan->fresh()->load('prices');
        });
    }

    /**
     * Toggle status of a Subscription Plan
     */
    public function toggleStatus($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            throw new \Exception('Subscription plan not found.');
        }

        $oldStatus = $plan->status;
        $plan->status = $plan->status === GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;

        $plan->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\SubscriptionPlan',
            'action_module' => 'Super Admin Subscriptions',
            'action_id' => $plan->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Subscription Plan Status',
            'description' => sprintf('Changed subscription plan "%s" (ID: %s) status from %s to %s.', $plan->name, $plan->id, $oldStatus, $plan->status),
            'module_accessed' => 'Super Admin Subscription Management',
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $plan->status],
        ]);

        return $plan->fresh()->load('prices');
    }

    /**
     * Delete a Subscription Plan
     */
    public function delete($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            throw new \Exception('Subscription plan not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\SubscriptionPlan',
            'action_module' => 'Super Admin Subscriptions',
            'action_id' => $plan->id,
            'action' => 'Delete',
            'log_name' => 'Delete Subscription Plan',
            'description' => sprintf('Deleted subscription plan "%s" (ID: %s).', $plan->name, $plan->id),
            'module_accessed' => 'Super Admin Subscription Management',
        ]);

        $plan->delete();

        return true;
    }
}
