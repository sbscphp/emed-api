<?php

namespace App\Services\SuperAdmin\ClientManagement;

use App\Enums\GeneralEnums;
use App\Exports\ClientUsageChargeExport;
use App\Models\ClientUsageCharge;
use App\Helpers\GeneralHelper;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Revamp\AuthenticationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ClientManagementService
{
    protected AuthenticationService $authenticationService;

    public function __construct(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function overview($request)
    {
        $query = Tenant::query()
            ->with('subscription.usageFee')
            ->when($request->search_param, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('email', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('registration_number', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('state_city', 'LIKE', '%' . $request->search_param . '%');
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->usage_fee_id, function ($query) use ($request) {
                $query->whereHas('subscription', function ($sub) use ($request) {
                    $sub->where('usage_fee_id', $request->usage_fee_id);
                });
            })
            ->when(($request->sort_by ?? null) === 'name_ascending', function ($query) {
                $query->orderBy('name', 'ASC');
            })
            ->when(($request->sort_by ?? null) === 'name_descending', function ($query) {
                $query->orderBy('name', 'DESC');
            });

        $paginate = $request->paginate ?? true;

        if ($paginate) {
            return $query->orderBy('id', 'DESC')->paginate($request->limit ?? 15);
        }

        return [
            'records' => $query->orderBy('id', 'DESC')->get(),
        ];
    }

    public function createClient($request)
    {
        $data = [
            'hospital_name' => $request->input('hospital_name'),
            'country' => $request->input('country'),
            'state_city' => $request->input('state_city'),
            'registration_number' => $request->input('registration_number'),
            'hospital_email' => $request->input('hospital_email'),
            'hospital_phoneno' => $request->input('hospital_phoneno'),
            'hospital_address' => $request->input('hospital_address'),
            'hospital_type' => $request->input('hospital_type'),
            'admin_firstname' => $request->input('admin_firstname'),
            'admin_lastname' => $request->input('admin_lastname'),
            'admin_email' => $request->input('admin_email'),
            'admin_phoneno' => $request->input('admin_phoneno'),
            'admin_password' => $request->input('admin_password'),
            'skip_email_verification' => true,
        ];

        $result = $this->authenticationService->create($data);
        /** @var \App\Models\Tenant $tenant */
        $tenant = $result['tenant'];

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'usage_fee_id' => $request->input('usage_fee_id'),
            'license_fee' => $request->input('license_fee'),
            'license_start_date' => $request->input('license_start_date'),
            'license_end_date' => $request->input('license_end_date'),
            'status' => GeneralEnums::ACTIVE->value,
        ]);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Create',
            'log_name' => 'Create Client',
            'description' => sprintf('Created client hospital %s.', $tenant->name),
            'module_accessed' => 'Super Admin Client Management',
        ]);

        $tenant->load('subscription.usageFee');

        return [
            'tenant' => $tenant,
            'user' => $result['user'],
            'subscription' => $subscription,
        ];
    }

    public function showClient($id)
    {
        return Tenant::with('subscription.usageFee')->find($id);
    }

    public function updateClient($id, $request)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        $oldData = $tenant->only(['name', 'country', 'state_city', 'registration_number', 'email', 'phone_number', 'address', 'hospital_type', 'status']);

        $tenant->update([
            'name' => $request->input('hospital_name'),
            'country' => $request->input('country'),
            'state_city' => $request->input('state_city'),
            'registration_number' => $request->input('registration_number'),
            'email' => $request->input('hospital_email'),
            'phone_number' => $request->input('hospital_phoneno'),
            'address' => $request->input('hospital_address'),
            'hospital_type' => $request->input('hospital_type'),
            'status' => $request->input('status', $tenant->status ?? GeneralEnums::ACTIVE->value),
        ]);

        $subscription = $tenant->subscription;

        if ($subscription) {
            $subscription->update([
                'usage_fee_id' => $request->input('usage_fee_id'),
                'license_fee' => $request->input('license_fee'),
                'license_start_date' => $request->input('license_start_date'),
                'license_end_date' => $request->input('license_end_date'),
            ]);
        } else {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'usage_fee_id' => $request->input('usage_fee_id'),
                'license_fee' => $request->input('license_fee'),
                'license_start_date' => $request->input('license_start_date'),
                'license_end_date' => $request->input('license_end_date'),
                'status' => GeneralEnums::ACTIVE->value,
            ]);
        }

        $newData = $tenant->fresh()->only(['name', 'country', 'state_city', 'registration_number', 'email', 'phone_number', 'address', 'hospital_type', 'status']);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Update',
            'log_name' => 'Update Client',
            'description' => sprintf('Updated client hospital %s.', $tenant->name),
            'module_accessed' => 'Super Admin Client Management',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);

        return $tenant->fresh()->load('subscription.usageFee');
    }

    public function showClientVisitCharges($id, $request)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        $query = ClientUsageCharge::with(['usageFee', 'updatedBy'])
            ->where('tenant_id', $id)
            // Search by the name of the user who last updated the charge
            ->when($request->search_param, function ($q) use ($request) {
                $q->whereHas('updatedBy', function ($u) use ($request) {
                    $u->where('firstname', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('lastname', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ['%' . $request->search_param . '%']);
                });
            })
            // Filter by charge status (e.g. Pending, Paid)
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            // Filter by billing month — accepts Y-m format e.g. 2026-04
            ->when($request->month, function ($q) use ($request) {
                try {
                    $date = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
                    $q->where('billing_month', $date->toDateString());
                } catch (\Throwable) {
                    // Invalid format — ignore the filter silently
                }
            })
            ->orderBy('billing_month', 'DESC');

        // Export branch — returns a downloadable Excel file
        if (filter_var($request->export, FILTER_VALIDATE_BOOLEAN)) {
            $records = $query->get();
            $filename = 'usage-charges-client-' . $id . '-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new ClientUsageChargeExport($records), $filename);
        }

        // Default: paginated response
        return $query->paginate($request->limit ?? 15);
    }

    public function updateClientVisitCharges($id, $request)
    {
        $charge = ClientUsageCharge::where('id', $id)->first();

        if (!$charge) {
            throw new \Exception('Usage charge not found.');
        }

        $currentUser = Auth::guard('api')->user();

        // if (!$currentUser) {
        //     throw new \Exception('Authenticated user not found.');
        // }

        $oldStatus = $charge->status;
        $charge->status = $request->input('status');
        $charge->updated_by = $currentUser?->id;
        $charge->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\ClientUsageCharge',
            'action_module' => 'Super Admin Clients',
            'action_id' => $charge->id,
            'action' => 'Update',
            'log_name' => 'Update Client Usage Charge',
            'description' => sprintf('Updated usage charge status from %s to %s for hospital %s.', $oldStatus, $charge->status, $charge->tenant?->name ?? 'Unknown'),
            'module_accessed' => 'Super Admin Client Management',
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $charge->status],
        ]);

        return $charge->load('usageFee', 'updatedBy:id,first_name,last_name');
    }

    public function toggleClientStatus($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        $tenant->status = $tenant->status === GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;
        $oldStatus = $tenant->status;
        $tenant->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Client Status',
            'description' => sprintf('Changed client %s status from %s to %s.', $tenant->name, $oldStatus, $tenant->status),
            'module_accessed' => 'Super Admin Client Management',
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $tenant->status],
        ]);

        return $tenant;
    }

    public function removeClient($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Delete',
            'log_name' => 'Remove Client',
            'description' => sprintf('Removed client hospital %s.', $tenant->name),
            'module_accessed' => 'Super Admin Client Management',
        ]);

        $tenant->delete();
    }
}
