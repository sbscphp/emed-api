<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResourceRecent;
use App\Models\Patient;
use App\Services\ServiceDepartment\ServiceDepartmentService;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;

class MainDashBoardStatsController extends Controller
{
    public $service_department_service;

    public function __construct(ServiceDepartmentService $service_department_service)
    {
        $this->service_department_service = $service_department_service;
    }
    public function index(Request $request)
    {

        try {
            $validated = $request->validate([
                "filter_calender" => 'nullable|string|in:daily,monthly, yearly'
            ]);

            $data = $this->service_department_service->main_dashboard($validated);
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching billing stats.', [], 500, $e);
        }
    }


    public function top_drugs()
    {
        try {
            $data = $this->service_department_service->top_drugs();
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }


    public function recent_patient(Request $request)
    {
        try {
            $validated = $request->validate([
                'search' => "nullable|string",
                'patient_type' => "nullable|string",
                'status' => "nullable|string",
            ]);
            $data = $this->service_department_service->recent_patient($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource
                $patient =  Patient::with('patient_visits_latest')->get();
                if (count($patient) == 0) {
                    return JsonResponser::send(true, 'No Data.', [], 500);
                }
                $exportData = PatientResourceRecent::collection($patient)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }
}
