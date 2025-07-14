<?php

namespace App\Repositories\BillingLog;

use App\Models\BillingLog;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FinancialReportExport;
use App\Helpers\ExportHelper;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class BillingLogRepository implements BillingLogRepositoryInterface
{
    public function create(array $data)
    {
        return BillingLog::create($data);
    }

    public function all($request)
    {
        $query = BillingLog::with([
            'serviceType' => function ($q) use ($request) {
                if (!empty($request['patient_service_type'])) {
                    $q->where('name', $request['patient_service_type']);
                }
            },
            'serviceUnit' => function ($q) use ($request) {
                if (!empty($request['patient_service_unit'])) {
                    $q->where('name', $request['patient_service_unit']);
                }
            },
            // patient_type
            'patient' => function ($q) use ($request) {
                if (!empty($request['patient_type'])) {
                    $q->where('patient_type', $request['patient_type']);
                }
            },
            'patient.service'
        ]);

        // if (!empty($request['search'])) {
        //     $search = $request['search'];
        //     $query->where(function ($q) use ($search) {
        //         $q->where('invoice_number', 'like', "%$search%")
        //             ->orWhere('item_name', 'like', "%$search%")
        //             ->orWhere('payment_status', 'like', "%$search%")
        //             ->orWhereHas('patient', function ($pq) use ($search) {
        //                 $pq->where('firstname', 'like', "%$search%")
        //                     ->orWhere('lastname', 'like', "%$search%")
        //                     ->orWhere('patientno', 'like', "%$search%")
        //                     ->orWhere('cardno', 'like', "%$search%");
        //             });
        //     });
        // }

        if (!empty($request['search'])) {
            $search = $request['search'];

            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%$search%")
                    ->orWhere('item_name', 'like', "%$search%")
                    ->orWhere('payment_status', 'like', "%$search%")
                    ->orWhereHas('patient', function ($pq) use ($search) {
                        $pq->where('firstname', 'like', "%$search%")
                            ->orWhere('lastname', 'like', "%$search%")
                            ->orWhere('patientno', 'like', "%$search%")
                            ->orWhere('cardno', 'like', "%$search%");
                    });
            });
        }

        if (!empty($request['payment_status'])) {
            $query->where('payment_status', $request['payment_status']);
        }

        // if (!empty($request['service_type_id'])) {
        //     $query->where('service_type_id', $request['service_type_id']);
        // }

        // if (!empty($request['service_unit_id'])) {
        //     $query->where('service_unit_id', $request['service_unit_id']);
        // }


        if (!empty($request['from']) && !empty($request['to'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request['from'])->startOfDay(),
                Carbon::parse($request['to'])->endOfDay()
            ]);
        }

        // if (!empty($request['export'])) {
        //     $billings = $query->get();

        //     $exportData = $billings->map(function ($item) {
        //         return [
        //             'Invoice Number' => $item->invoice_number,
        //             'Patient Name' => $item->patient->firstname . ' ' . $item->patient->lastname,
        //             'Patient No' => $item->patient->patientno,
        //             'Card No' => $item->patient->cardno,
        //             'Billing Date' => $item->billing_date,
        //             'Item Name' => $item->item_name,
        //             'Quantity' => $item->quantity,
        //             'Unit Price' => $item->unit_price,
        //             'Sub Total' => $item->sub_total,
        //             'Tax Amount' => $item->tax_amount,
        //             'Grand Total' => $item->grand_total,
        //             'Payment Method' => $item->payment_method,
        //             'Payment Status' => $item->payment_status,
        //             'Service Type' => $item->serviceType->name ?? '',
        //             'Service Unit' => $item->serviceUnit->name ?? '',
        //             'Created At' => $item->created_at->toDateTimeString(),
        //         ];
        //     });



        //       return response()->json($exportData);
        // }

        // Default paginate
        return $query->latest()->paginate(10);
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
        $export = $request->export;
        $is_paginated = $request->is_paginated;
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
            if ($export == 'xlsx') {
                return Excel::download(new FinancialReportExport($report), 'financial_report_' . now()->format('Ymd_His') . '.xlsx');
            } else if ($export == 'pdf') {
                $pdf = Pdf::loadView('reports.financial_report', [
                    'report' => $report,
                    'totalrevenue' => $totalRevenue,
                    'totalpending' => $totalPending
                ]);

                return $pdf->download('financial_report_' . now()->format('Ymd_His') . '.pdf');
            }
        }

        $paginated = new LengthAwarePaginator(
            $report->forPage($currentPage, $perPage),
            $report->count(),
            $perPage,
            $currentPage,
            ['path' => url()->current(), 'query' => $request->query()]
        );
        $data = $is_paginated ? $paginated : $report;
        return JsonResponser::send(false, 'Financial Report Generated Successfully.', [
            'data' => $data,
            'sub_totals' => [
                'total_revenue' => $totalRevenue,
                'pending_payment' => $totalPending,
            ],
        ]);
    }
}
