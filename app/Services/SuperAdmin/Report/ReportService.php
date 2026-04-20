<?php

namespace App\Services\SuperAdmin\Report;

use App\Models\ClientUsageCharge;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get revenue report with date filtering and search
     */
    public function revenueReport($request)
    {
        $startDate = $request->start_date ? Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay() : null;

        $query = Subscription::query()
            ->with('tenant', 'usageFee')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('license_start_date', [$startDate, $endDate])
                    ->orWhereBetween('license_end_date', [$startDate, $endDate]);
            })
            ->when($request->search_param, function ($query) use ($request) {
                $query->whereHas('tenant', function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search_param . '%');
                });
            });

        $limit = $request->limit ?? 15;
        $paginate = $request->paginate ?? true;

        if ($paginate) {
            return $query->orderBy('subscriptions.id', 'DESC')->paginate($limit);
        }

        return [
            'records' => $query->orderBy('subscriptions.id', 'DESC')->get(),
        ];
    }

    /**
     * Get client report with date filtering and search
     */
    public function clientReport($request)
    {
        $startDate = $request->start_date ? Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay() : null;

        $query = Tenant::query()
            ->with('subscription.usageFee')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereHas('subscription', function ($sub) use ($startDate, $endDate) {
                    $sub->whereBetween('license_start_date', [$startDate, $endDate])
                        ->orWhereBetween('license_end_date', [$startDate, $endDate]);
                });
            })
            ->when($request->search_param, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search_param . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->search_param . '%')
                    ->orWhere('registration_number', 'LIKE', '%' . $request->search_param . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        $limit = $request->limit ?? 15;
        $paginate = $request->paginate ?? true;

        if ($paginate) {
            return $query->orderBy('id', 'DESC')->paginate($limit);
        }

        return [
            'records' => $query->orderBy('id', 'DESC')->get(),
        ];
    }

    /**
     * Get usage fee report with date filtering and search
     */
    public function usageFeeReport($request)
    {
        $startDate = $request->start_date ? Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay() : null;

        $query = ClientUsageCharge::query()
            ->with('tenant', 'usageFee', 'updatedBy:id,first_name,last_name')
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('billing_month', [$startDate, $endDate]);
            })
            ->when($request->search_param, function ($query) use ($request) {
                $query->whereHas('tenant', function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search_param . '%');
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        $limit = $request->limit ?? 15;
        $paginate = $request->paginate ?? true;

        if ($paginate) {
            return $query->orderBy('id', 'DESC')->paginate($limit);
        }

        return [
            'records' => $query->orderBy('id', 'DESC')->get(),
        ];
    }
}
