<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResources;
use App\Models\AuditLog;
use App\Responser\JsonResponser;
use App\Services\BillingLog\BillingLogService;
use App\Services\Patient\PatientService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected $billingService;
    protected $userService;
    protected $patientService;

    public function __construct(BillingLogService $billingService, UserService $userService, PatientService $patientService)
    {
        $this->billingService = $billingService;
        $this->userService = $userService;
        $this->patientService = $patientService;
    }

    public function index(Request $request)
    {
        try {
            $records = $this->billingService->all($request);
            if (
                $records instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
                $records instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            ) {
                return $records;
            }

            if ($records->isEmpty()) {
                return JsonResponser::send(true, 'No reports found.', [], 200);
            }

            return JsonResponser::send(false, 'Reports logs retrieved successfully', $records, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getReportStatistics()
    {
        try {
            $totalPatients = $this->patientService->getRecordStats();
            $monthlyRevenue = $this->billingService->getMonthlyRevenue();
            $pendingPayment = $this->billingService->getPendingPayment();
            $completedPayment = $this->billingService->getCompletedPayment();

            return JsonResponser::send(false, 'Report statistics fetched successfully.', [
                'total_patients' => $totalPatients,
                'monthly_revenue' => $monthlyRevenue,
                'pending_payment' => $pendingPayment,
                'completed_payment' => $completedPayment,
            ]);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching report statistics.', [], 500, $e);
        }
    }

    public function getPatientReport(Request $request)
    {
        return $this->patientService->getPatientReport($request);
    }

    public function getFinancialReport(Request $request)
    {
        return $this->billingService->getFinancialReport($request);
    }

    public function getAllSystemReport(Request $request)
    {
        return $this->userService->getSystemReport($request);
    }

    public function user_activity(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validate =  $request->validate([
                'name' => "nullable|string",
                "action_type" => "nullable|string",
                "limit" => "nullable|numeric",
                "export" => "nullable|in:csv,pdf",
                "is_download" => "nullable|boolean",
                'status' => "nullable|string"
            ]);
            if (!empty($validate['is_download'])) {

                if ($validate['export'] == 'csv') {

                    $data =  AuditLog::with('audit_log_transactions')->when(!empty($validate['action_type']), function ($query) use ($validate) {
                        //$query->where("user_id", $validate['user_id'])
                        $query->where('action_type', $validate['action_type']);
                    })->get();

                    $convertdata = AuditResources::collection($data)->resolve();
                    return ExportHelper::streamCsv($convertdata, null, 'user_activity.csv');
                } else if ($validate['export'] == 'pdf') {

                    $data =  AuditLog::with('audit_log_transactions')->when(!empty($validate['action_type']), function ($query) use ($validate) {
                        // $query->where("user_id", $validate['user_id'])
                        //     ->orWhere('action_type', $validate['action_type']);
                        $query->where('action_type', $validate['action_type']);
                    })->get();

                    $convertdata = AuditResources::collection($data)->resolve();
                    return ExportHelper::downloadPdf($convertdata, 'user_activity.pdf');
                }
            }
            $data = $this->userService->user_activity($validate);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'fetched successfully.', $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Error fetching report statistics.', [], 500, $th);
        }
    }
}
