<?php

namespace App\Http\Controllers\v1\SuperAdmin\Report;

use App\Exports\ClientReportExport;
use App\Exports\RevenueReportExport;
use App\Exports\UsageFeeReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ReportRequest;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\Report\ReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function revenueReport(ReportRequest $request)
    {
        try {
            // Export branch
            if (filter_var($request->export, FILTER_VALIDATE_BOOLEAN)) {
                $records = $this->reportService->revenueReport($request->merge(['paginate' => false]));
                $records = $records['records'] ?? $records;

                // Map records to array format for export
                $mappedRecords = $records->map(function ($subscription) {
                    return [
                        'hospital_name' => $subscription->tenant->name ?? 'N/A',
                        'revenue' => $subscription->license_fee ?? 0,
                        'paid' => 0, // You can adjust based on your payment tracking
                        'renewal_date' => $subscription->license_end_date ? $subscription->license_end_date->format('Y-m-d') : 'N/A',
                    ];
                });

                $filename = 'revenue-report-' . now()->format('Y-m-d') . '.xlsx';
                return Excel::download(new RevenueReportExport($mappedRecords), $filename);
            }

            // Default: paginated response
            $records = $this->reportService->revenueReport($request);
            return JsonResponser::send(false, 'Revenue report generated successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to generate revenue report', 400);
        }
    }

    public function clientReport(ReportRequest $request)
    {
        try {
            // Export branch
            if (filter_var($request->export, FILTER_VALIDATE_BOOLEAN)) {
                $records = $this->reportService->clientReport($request->merge(['paginate' => false]));
                $records = $records['records'] ?? $records;

                $filename = 'client-report-' . now()->format('Y-m-d') . '.xlsx';
                return Excel::download(new ClientReportExport($records), $filename);
            }

            // Default: paginated response
            $records = $this->reportService->clientReport($request);
            return JsonResponser::send(false, 'Client report generated successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to generate client report', 400);
        }
    }

    public function usageFeeReport(ReportRequest $request)
    {
        try {
            // Export branch
            if (filter_var($request->export, FILTER_VALIDATE_BOOLEAN)) {
                $records = $this->reportService->usageFeeReport($request->merge(['paginate' => false]));
                $records = $records['records'] ?? $records;

                $filename = 'usage-fee-report-' . now()->format('Y-m-d') . '.xlsx';
                return Excel::download(new UsageFeeReportExport($records), $filename);
            }

            // Default: paginated response
            $records = $this->reportService->usageFeeReport($request);
            return JsonResponser::send(false, 'Usage fee report generated successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to generate usage fee report', 400);
        }
    }
}
