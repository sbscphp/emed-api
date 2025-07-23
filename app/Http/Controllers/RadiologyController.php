<?php

namespace App\Http\Controllers;

use App\Models\Radiology;
use App\Services\Radiology\RadiologyService;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;
use App\Helpers\ExportHelper;
use App\Http\Resources\RadiologyResourceAll;
use App\Models\Patient;

class RadiologyController extends Controller
{
    public $radiologyService;


    public function __construct(RadiologyService $radiologyService)
    {
        $this->radiologyService = $radiologyService;
    }
    public function index(Request $request)
    {

        $validated =   $request->validate([
            'export' => "nullable|string",
            'search' => 'nullable|string',
            'start_date' => "nullable|string",
            'end_date' => "nullable|string",
            'phone_number' => "nullable|string",
            'payment_status' => "nullable|string",
            'test_status' => "nullable|string",
        ]);


        $radiology =  Radiology::with(['patient.patient_visits.billingLogsForPatient', 'pharmacist'])->get();
        if (count($radiology) == 0) {
            return JsonResponser::send(false, 'No Data.', [], 200);
        }
        $data = $this->radiologyService->radiology_list($validated);
        if (!empty($validated['export'])) {
            $export =  $validated['export'];
            // $exportData = PharmacyResourceList::collection($pharm)->resolve();
            $exportData = RadiologyResourceAll::collection($radiology)->resolve();
            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
            }
        }
        return JsonResponser::send(false, 'Billing records retrieved successfully.', $data, 200);
    }


    public function patient(Request $request)
    {
        $validated =   $request->validate([
            'export' => "nullable|string",
            'search' => 'nullable|string',
            'start_date' => "nullable|string",
            'end_date' => "nullable|string",
            'phone_number' => "nullable|string",
            'payment_status' => "nullable|string",
            'test_status' => "nullable|string",
            'patient_id' => "required|numeric"
        ]);

        $radiology =  Radiology::with(['patient.visits_recent.billingLogsForPatient', 'pharmacist'])
            ->whereHas('patient', function ($q) use ($validated) {
                $q->where('id', $validated['patient_id']);
            })
            ->get();

        if (count($radiology) == 0) {
            return JsonResponser::send(false, 'No Data.', [], 200);
        }
        $data = $this->radiologyService->radiology_patient($validated);
        if (!empty($validated['export'])) {
            $export =  $validated['export'];
            // $exportData = PharmacyResourceList::collection($pharm)->resolve();
            $exportData = RadiologyResourceAll::collection($radiology)->resolve();
            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
            }
        }

        $data = [
            'data' => $radiology,
            'patient' => Patient::with('visits_recent')->first()
        ];

        return JsonResponser::send(false, 'Billing records retrieved successfully.', $data, 200);
    }
}
