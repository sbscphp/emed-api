<?php

namespace App\Services\SuperAdmin\Subscription;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Models\Subscription;
use App\Models\Tenant;

class SubscriptionService
{
    public function overview($request)
    {
        $query = Subscription::query()
            ->with('tenant', 'usageFee')
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

    public function create($request)
    {
        // Check if tenant already has an active subscription
        $existingSubscription = Subscription::where('tenant_id', $request->input('tenant_id'))
            ->where('status', GeneralEnums::ACTIVE->value)
            ->first();

        if ($existingSubscription) {
            throw new \Exception('This hospital already has an active subscription.');
        }

        // Check if tenant exists
        $tenant = Tenant::find($request->input('tenant_id'));
        if (!$tenant) {
            throw new \Exception('Hospital/Tenant not found.');
        }

        $subscription = Subscription::create([
            'tenant_id' => $request->input('tenant_id'),
            'usage_fee_id' => $request->input('usage_fee_id'),
            'license_fee' => config('app.default_license_fee', 50000),
            'license_start_date' => $request->input('license_start_date'),
            'license_end_date' => $request->input('license_end_date'),
            'status' => GeneralEnums::ACTIVE->value,
        ]);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Subscription',
            'action_module' => 'Super Admin Subscriptions',
            'action_id' => $subscription->id,
            'action' => 'Create',
            'log_name' => 'Create Subscription',
            'description' => sprintf('Created subscription for tenant %s.', $subscription->tenant_id),
            'module_accessed' => 'Super Admin Subscription Management',
        ]);

        return $subscription->load('tenant', 'usageFee');
    }

    public function show($id)
    {
        $subscription = Subscription::with('tenant', 'usageFee')->find($id);

        if (!$subscription) {
            throw new \Exception('Subscription not found.');
        }

        return $subscription;
    }

    public function update($id, $request)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            throw new \Exception('Subscription not found.');
        }

        $data = [];

        if ($request->has('usage_fee_id')) {
            $data['usage_fee_id'] = $request->input('usage_fee_id');
        }

        if ($request->has('license_fee')) {
            $data['license_fee'] = $request->input('license_fee');
        }

        if ($request->has('license_start_date')) {
            $data['license_start_date'] = $request->input('license_start_date');
        }

        if ($request->has('license_end_date')) {
            $data['license_end_date'] = $request->input('license_end_date');
        }

        $oldData = $subscription->only(['usage_fee_id', 'license_fee', 'license_start_date', 'license_end_date', 'status']);
        $subscription->update($data);
        $newData = $subscription->fresh()->only(['usage_fee_id', 'license_fee', 'license_start_date', 'license_end_date', 'status']);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Subscription',
            'action_module' => 'Super Admin Subscriptions',
            'action_id' => $subscription->id,
            'action' => 'Update',
            'log_name' => 'Update Subscription',
            'description' => sprintf('Updated subscription %s for tenant %s.', $subscription->id, $subscription->tenant_id),
            'module_accessed' => 'Super Admin Subscription Management',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);

        return $subscription->fresh()->load('tenant', 'usageFee');
    }

    public function toggleStatus($id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            throw new \Exception('Subscription not found.');
        }

        $subscription->status = $subscription->status === GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;

        $oldStatus = $subscription->status;
        $subscription->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Subscription',
            'action_module' => 'Super Admin Subscriptions',
            'action_id' => $subscription->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Subscription Status',
            'description' => sprintf('Changed subscription %s status from %s to %s.', $subscription->id, $oldStatus, $subscription->status),
            'module_accessed' => 'Super Admin Subscription Management',
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $subscription->status],
        ]);

        return $subscription->fresh()->load('tenant', 'usageFee');
    }

    public function delete($id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            throw new \Exception('Subscription not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Subscription',
            'action_module' => 'Super Admin Subscriptions',
            'action_id' => $subscription->id,
            'action' => 'Delete',
            'log_name' => 'Delete Subscription',
            'description' => sprintf('Deleted subscription %s for tenant %s.', $subscription->id, $subscription->tenant_id),
            'module_accessed' => 'Super Admin Subscription Management',
        ]);

        $subscription->delete();

        return true;
    }
}
