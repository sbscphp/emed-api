<?php

namespace App\Repositories\BillingLog;

use App\Models\BillingLog;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FinancialReportExport;


class BillingLogRepository implements BillingLogRepositoryInterface
{
    public function create(array $data)
    {
        return BillingLog::create($data);
    }

    public function all()
    {
        return BillingLog::with(['serviceType', 'serviceUnit', 'patient.service'])->latest()->paginate(10);
    }

    public function find($id)
    {
        return BillingLog::with(['serviceType', 'serviceUnit', 'patient'])->find($id);
    }

    public function update($id, array $data)
    {
        $log = BillingLog::findOrFail($id);
        $log->update($data);
        return $log;
    }

    public function delete($id)
    {
        return BillingLog::destroy($id);
    }

    public function getLatest()
    {
        return BillingLog::orderBy('id', 'desc')->first();
    }

    public function getMonthlyRevenue(): float
    {
        return BillingLog::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('grand_total');
    }

    public function getPendingPayment(): float
    {
        return BillingLog::where('payment_status', 'pending')->sum('grand_total');
    }

    public function getCompletedPayment(): float
    {
        return BillingLog::where('payment_status', 'paid')->sum('grand_total');
    }

    public function getFinancialReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = $request->start_date;
        $end = $request->end_date;
        $download = $request->boolean('download', false);
        $perPage = $request->integer('per_page', 10);
        $currentPage = $request->integer('page', 1);

        $billingLogs = BillingLog::with('serviceType')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        if ($billingLogs->isEmpty()) {
            return JsonResponser::send(false, 'No financial records found for the selected date range.', [
                'data' => [],
                'sub_totals' => [
                    'total_revenue' => 0,
                    'pending_payment' => 0,
                ],
            ]);
        }

        $grouped = $billingLogs->groupBy(fn($log) => optional($log->serviceType)->name ?? 'Unknown');

        $report = $grouped->map(function ($group, $dept) {
            return [
                'department' => $dept,
                'total_revenue' => $group->sum('grand_total'),
                'pending_payment' => $group->where('payment_status', 'pending')->sum('grand_total'),
            ];
        })->values();

        $totalRevenue = $report->sum('total_revenue');
        $totalPending = $report->sum('pending_payment');

        if ($download) {
            return Excel::download(new FinancialReportExport($report), 'financial_report_' . now()->format('Ymd_His') . '.xlsx');
        }

        $paginated = new LengthAwarePaginator(
            $report->forPage($currentPage, $perPage),
            $report->count(),
            $perPage,
            $currentPage,
            ['path' => url()->current(), 'query' => $request->query()]
        );

        return JsonResponser::send(false, 'Financial Report Generated Successfully.', [
            'data' => $paginated,
            'sub_totals' => [
                'total_revenue' => $totalRevenue,
                'pending_payment' => $totalPending,
            ],
        ]);
    }
}
