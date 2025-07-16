<?php

namespace App\Http\Controllers\v1\Admin;

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

class AuditLogController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
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

            $logs = $this->auditLogService->getAllAuditLogs(
                $search,
                $sortBy,
                $startDate,
                $endDate,
                $activityType,
                $paginate,
                $export
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

    public function downloadAuditLog($downloadType, AuditLogRequest $request)
    {
        try {
            DB::connection('tenant');

            $search = $request->search;
            $sortBy = $request->sort_by ?? 'oldest';
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $activityType = $request->activity_type;
            $paginate = false;

            $logs = $this->auditLogService->getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate, $downloadType);

            if ($logs->isEmpty()) {
                return JsonResponser::send(true, 'Record(s) not found for download.', null, 200);
            }

            switch (strtolower($downloadType)) {
                case 'csv':
                    return $this->exportCsv($logs);
                    break;

                case 'pdf':
                    return $this->exportPdf($logs);
                    break;

                default:
                    return JsonResponser::send(true, 'Invalid download type.', null, 400);
            }

            return JsonResponser::send(true, 'Record(s) found successfully.', $logs);
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
                "search" => "nullable|string"
            ]);
            // $search = $validate['search'];
            config(['database.default' => 'tenant']);
            DB::connection('tenant');
            $medical_log = AuditLog::whereIn('action_type', [
                'App\Models\MedicalHistory',
                'App\Models\Medication',
                'App\Models\MedicineType',
                'Models\MedicineType',
                // 'Models\Pharmacy',
                'Models\MedicineInventory',
                'Models\Medicine'
            ])
                ->when(!empty($validate['search']), function ($query, $validate) {
                    $query->where('action_type', 'LIKE', "%{$validate['search']}%");
                })->paginate(10);


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
}
