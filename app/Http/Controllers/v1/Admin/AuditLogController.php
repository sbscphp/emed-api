<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogRequest;
use App\Responser\JsonResponser;
use App\Services\AuditLog\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response as FacadesResponse;
use App\Models\AuditLog;
use App\Models\Medicine_Log;
use App\Http\Resources\MedicineLogResouces;

class AuditLogController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function userActivityRecords(Request $request)
    {

        try {
            $overview = $this->auditLogService->activityOverview($request);

            $stats = $this->auditLogService->activityStats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if ($request['export'] === 'csv') {
                return $this->auditLogService->activityExport($overview);
            }

            if ($request['export'] === 'pdf') {
                $pdf = Pdf::loadView('exports.audit_logs', ['logs' => $overview]);
                return $pdf->download('audit_logs.pdf');
            }

            if (!$request['paginate']) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function userActivity(AuditLogRequest $request)
    {
        try {
            DB::connection('tenant');

            $search = $request->search;
            $sortBy = $request->sort_by ?? 'recent';
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $activityType = $request->activity_type;
            $paginate = $request->paginate ?? false;
            $export = $request->export;
            $action = $request->action;
            $module_accessed = $request->module_accessed;

            $logs = $this->auditLogService->getAllAuditLogs(
                $search,
                $sortBy,
                $startDate,
                $endDate,
                $activityType,
                $paginate,
                $export,
                $action,
                $module_accessed
            );

            if ($export === 'csv' || $export === 'pdf') {
                return $logs;
            }

            if ($logs->isEmpty()) {
                return JsonResponser::send(true, 'Record(s) not found.', null, 200);
            }

            $response = [
                'logs' => $logs,
                'total' => $paginate ? $logs->total() : $logs->count()
            ];

            return JsonResponser::send(true, 'Record(s) found successfully.', $response);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server error.', [], 500, $th);
        }
    }

    public function downloadAuditLog(AuditLogRequest $request)
    {
        try {
            DB::connection('tenant');
            $downloadType = $request->downloadType;
            // $search = $request->search;
            // $sortBy = $request->sort_by ?? 'oldest';
            // $startDate = $request->start_date;
            // $endDate = $request->end_date;
            // $activityType = $request->activity_type;
            // $paginate = $request->paginate ?? false;
            // $export = $request->export;
            // $action = $request->action;
            // $module_accessed = $request->module_accessed;

            // $logs = $this->auditLogService->getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate, $downloadType, $export, $action, $module_accessed);

            // if ($logs->isEmpty()) {
            //     return JsonResponser::send(true, 'Record(s) not found for download.', null, 200);
            // }

            $overview = $this->auditLogService->activityOverview($request);
            $exportData = $overview->map(function ($log) {
                return [
                    'User ID'         => $log->causer?->id ?? 'System',
                    'User Name'       => $log->causer?->fullname ?? 'System',
                    'User Role'       => $log->causer?->role ?? 'System',
                    'Timestamp'       => $log->created_at->toDateTimeString(),
                    'Action Taken'    => $log->action,
                    'Module Accessed' => $log->module_accessed,
                ];
            })->toArray();

            if ($downloadType === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'logs.csv');
            }

            if ($downloadType === 'pdf') {
                $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                    ->setPaper('A1', 'landscape');
                return $pdf->download('logs.pdf');
            }

            // return JsonResponser::send(true, 'Invalid download type.', null, 400);

            return JsonResponser::send(true, 'Record(s) found successfully.', $overview);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server error.', [], 500, $th);
        }
    }

    private function exportCsv($logs)
    {
        config(['database.default' => 'tenant']);
        $filename = 'audit_logs_' . now()->format('YmdHis') . '.csv';

        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User ID', 'User Role', 'Action', 'Module Accessed', 'Date']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->causer?->id,
                    $log->causer?->role,
                    $log->log_name,
                    $log->action_type,
                    $log->created_at
                ]);
            }
            fclose($file);
        };

        return FacadesResponse::stream($callback, 200, $headers);
    }

    private function exportPdf($logs)
    {
        $pdf = Pdf::loadView('exports.audit_logs', ['logs' => $logs]);
        return $pdf->download('audit_logs.pdf');
    }


    public function fetch_medical_log(Request $request)
    {


        try {
            $validate = $request->validate([
                "patient_status" => "nullable|string",
                'status' => "nullable|string",
                "export" => "nullable|string"
            ]);
            // $search = $validate['search'];
            config(['database.default' => 'tenant']);
            DB::connection('tenant');
            $medical_log = Medicine_Log::with(["patient", "medication", "pharmacy"])
                ->when(!empty($validate['patient_status']), function ($query, $validate) {
                    $query->where('patient_status', 'LIKE', "%{$validate['patient_status']}%");
                })
                ->when(!empty($validate['status']), function ($query, $validate) {
                    $query->where('status', 'LIKE', "%{$validate['status']}%");
                })
                ->paginate(10);

            if (!empty($validate['export']) && $validate['export'] == 'pdf') {
                $medical_log = Medicine_Log::with(["patient", "medication", "pharmacy"])->get();
                $data = MedicineLogResouces::collection($medical_log)->resolve();
                return ExportHelper::downloadPdf($data, 'medicine.pdf');
            } else if (!empty($validate['export']) && $validate['export'] == 'csv') {
                $medical_log = Medicine_Log::with(["patient", "medication", "pharmacy"])->get();
                $data = MedicineLogResouces::collection($medical_log)->resolve();
                return ExportHelper::streamCsv($data, null, 'medicine_' . now()->format('Ymd_His') . '.csv');
            }

            if ($medical_log->isNotEmpty()) {
                DB::connection('tenant')->commit();
                return JsonResponser::send(true, 'Record(s) found successfully.', $medical_log);
            } else {
                return JsonResponser::send(false, 'No record found.', []);
            }
        } catch (\Throwable $th) {
            return JsonResponser::send(false, 'Internal Server Error.', [], 500, $th);
        }
    }


    public function data_changes(Request $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();
            $validated = $request->validate([
                "search" => "nullable|string",
                "start_date" => "nullable|date",
                "end_date" => "nullable|date",
                "activity_type" => "nullable|string",
                'export' => 'nullable|string',
                'paginate' => 'nullable|in:1,0',
                'module_accessed' => "nullable|string",
                'action' => "nullable|string",
            ]);

            $logs = $this->auditLogService->data_changes($validated);
            if (!empty($validated['export'])) {
                $query = AuditLog::whereIn('module_accessed', ['Billing', 'Records', 'Pharmacy'])->with(['audit_log_transactions', 'causer']);
                $logs = $query->get();
                $export = $validated['export'];

                $exportData = $logs->map(function ($log) {
                    return [
                        'User ID' => $log->causer->id ?? 'N/A',
                        'Module' => $log->log_name,
                        'Timestamp' => $log->created_at->toDateTimeString(),
                        'Reason for update' => $log->description
                    ];
                });

                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'audit-logs.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'audit-logs.pdf');
                }
            }


            return JsonResponser::send(false, 'Record(s) found successfully.', $logs);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal Server Error.', [], 500, $th);
        }
    }
}
