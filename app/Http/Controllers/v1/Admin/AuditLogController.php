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

            $logs = $this->auditLogService->getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate);

            if($logs->isEmpty()){
                return JsonResponser::send(true, 'Record(s) not found.', null, 404);
            }

            $response = [
                'logs' => $logs,
                'total' => $logs->total()
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

            $logs = $this->auditLogService->getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate);

            if($logs->isEmpty()){
                return JsonResponser::send(true, 'Record(s) not found for download.', null, 404);
            }

            switch(strtolower($downloadType)){
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
        $filename = 'audit_logs_'. now()->format('YmdHis') . '.csv';

        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
        ];

        $callback = function() use ($logs){
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User ID', 'User Role', 'Action', 'Module Accessed', 'Date']); // Headers

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
}
