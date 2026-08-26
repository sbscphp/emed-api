<?php

namespace App\Services\SuperAdmin\Configurations;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Models\UsageFee;

class ConfigurationService
{
    /**
     * Get usage fees
     */
    public function usageFees($request)
    {
        $query = UsageFee::query()
            ->when($request->search_param, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search_param . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when(($request->sort_by ?? null) === 'name_ascending', function ($query) {
                $query->orderBy('name', 'ASC');
            })
            ->when(($request->sort_by ?? null) === 'name_descending', function ($query) {
                $query->orderBy('name', 'DESC');
            });

        $paginate = $request->paginate ?? true;
        if ($paginate != "false") {
            return $query->orderBy('id', 'DESC')->paginate($request->limit ?? 15);
        }

        return [
            'records' => $query->orderBy('id', 'DESC')->get(),
        ];
    }

    public function stats($request)
    {
        $query = UsageFee::query();
        return [
            'total_usage_fees' => (clone $query)->count(),
            'active_usage_fees' => (clone $query)->where('status', GeneralEnums::ACTIVE->value)->count(),
            'inactive_usage_fees' => (clone $query)->where('status', GeneralEnums::INACTIVE->value)->count(),
        ];
    }

    public function createUsageFee($request)
    {
        $inputCycles = $request->input('cycles');

        $normalizedCycles = null;
        if (is_array($inputCycles)) {
            $normalizedCycles = [];
            foreach (['monthly', 'quarterly', 'yearly'] as $key) {
                if (array_key_exists($key, $inputCycles)) {
                    $normalizedCycles[$key] = $inputCycles[$key];
                } elseif (array_key_exists(ucfirst($key), $inputCycles)) {
                    $normalizedCycles[$key] = $inputCycles[ucfirst($key)];
                }
            }
        }

        $amount = $request->input('amount');
        if ($amount === null && is_array($normalizedCycles) && array_key_exists('monthly', $normalizedCycles)) {
            $amount = $normalizedCycles['monthly'];
        }

        $usageFee = UsageFee::create([
            'name' => trim($request->input('name')),
            'is_general_visit' => $request->boolean('is_general_visit'),
            'is_unique_visit' => $request->boolean('is_unique_visit'),
            'amount' => $amount ?? 0,
            'cycles' => $normalizedCycles,
            'status' => $request->input('status', GeneralEnums::ACTIVE->value),
        ]);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\UsageFee',
            'action_module' => 'Super Admin Usage Fees',
            'action_id' => $usageFee->id,
            'action' => 'Create',
            'log_name' => 'Create Usage Fee',
            'description' => sprintf('Created usage fee %s.', $usageFee->name),
            'module_accessed' => 'Super Admin Configuration Management',
        ]);

        return $usageFee;
    }

    public function showUsageFee($id)
    {
        return UsageFee::find($id);
    }

    public function updateUsageFee($id, $request)
    {
        $usageFee = UsageFee::find($id);

        if (!$usageFee) {
            throw new \Exception('Usage fee not found.');
        }

        $oldData = $usageFee->only(['name', 'is_general_visit', 'is_unique_visit', 'amount', 'cycles', 'status']);

        $inputCycles = $request->input('cycles');

        $normalizedCycles = $usageFee->cycles;
        if (is_array($inputCycles)) {
            $normalizedCycles = [];
            foreach (['monthly', 'quarterly', 'yearly'] as $key) {
                if (array_key_exists($key, $inputCycles)) {
                    $normalizedCycles[$key] = $inputCycles[$key];
                } elseif (array_key_exists(ucfirst($key), $inputCycles)) {
                    $normalizedCycles[$key] = $inputCycles[ucfirst($key)];
                }
            }
        }

        $amount = $request->input('amount', $usageFee->amount);
        if ($amount === null && is_array($normalizedCycles) && array_key_exists('monthly', $normalizedCycles)) {
            $amount = $normalizedCycles['monthly'];
        }

        $usageFee->update([
            'name' => trim($request->input('name')),
            'is_general_visit' => $request->boolean('is_general_visit'),
            'is_unique_visit' => $request->boolean('is_unique_visit'),
            'amount' => $amount,
            'cycles' => $normalizedCycles,
            'status' => $request->input('status'),
        ]);

        $newData = $usageFee->fresh()->only(['name', 'is_general_visit', 'is_unique_visit', 'amount', 'cycles', 'status']);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\UsageFee',
            'action_module' => 'Super Admin Usage Fees',
            'action_id' => $usageFee->id,
            'action' => 'Update',
            'log_name' => 'Update Usage Fee',
            'description' => sprintf('Updated usage fee %s.', $usageFee->name),
            'module_accessed' => 'Super Admin Configuration Management',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);

        return $usageFee->fresh();
    }

    public function toggleUsageFeeStatus($id)
    {
        $usageFee = UsageFee::find($id);

        if (!$usageFee) {
            throw new \Exception('Usage fee not found.');
        }

        $usageFee->status = $usageFee->status === GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;
        $previousStatus = $usageFee->status;
        $usageFee->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\UsageFee',
            'action_module' => 'Super Admin Usage Fees',
            'action_id' => $usageFee->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Usage Fee Status',
            'description' => sprintf('Changed usage fee %s status from %s to %s.', $usageFee->name, $previousStatus, $usageFee->status),
            'module_accessed' => 'Super Admin Configuration Management',
            'old_data' => ['status' => $previousStatus],
            'new_data' => ['status' => $usageFee->status],
        ]);

        return $usageFee;
    }

    public function removeUsageFee($id)
    {
        $usageFee = UsageFee::find($id);

        if (!$usageFee) {
            throw new \Exception('Usage fee not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\UsageFee',
            'action_module' => 'Super Admin Usage Fees',
            'action_id' => $usageFee->id,
            'action' => 'Delete',
            'log_name' => 'Delete Usage Fee',
            'description' => sprintf('Deleted usage fee %s.', $usageFee->name),
            'module_accessed' => 'Super Admin Configuration Management',
        ]);

        $usageFee->delete();
    }
}
