<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\BillingLog\BillingLogService;
use App\Services\Patient\PatientService;
use App\Services\User\UserService;
use Illuminate\Http\Request;


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
                return JsonResponser::send(true, 'No reports found.', [], 204);
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
}
