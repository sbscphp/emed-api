<?php

namespace App\Http\Controllers;

use App\Models\Radiology;
use App\Services\Radiology\RadiologyService;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;
use App\Helpers\ExportHelper;
use App\Http\Resources\RadiologyResourceAll;
use App\Models\Patient;
use App\Models\Radiology_lab_patient;
use App\Http\Requests\Radiology_examination_request;
use App\Models\Radiology_lab_patient_examination;
use Illuminate\Support\Facades\Validator;

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

    public function radiology_examination(Radiology_examination_request $request)
    {
        // Radiology_lab_patient
        $validated  = $request->validated();

        $radiology = Radiology_lab_patient::create([
            'patient_id' =>  $validated['patient_id'],
            'test_name' => $validated['test_name'],
            'user_id' => $validated['doctor_id']
        ]);


        $all_exam = json_decode($validated['all_exam'], true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($all_exam)) {
            return JsonResponser::send(false, 'Invalid JSON format for all_exam', 422);
        }

        foreach ($all_exam as $index => $exam) {
            $examValidator = Validator::make($exam, [
                'examination' => 'required|string|max:255',
                'result' => 'required|numeric|max:255',
                'unit' => 'required|numeric|max:50',
                'normal_values' => 'required|numeric|max:100',
            ]);

            if ($examValidator->fails()) {

                return JsonResponser::send(false, $examValidator->errors(), 200);
            }

            Radiology_lab_patient_examination::create([
                'radiology_lab_patients_id' => $radiology->id,
                'examination' => $exam['examination'],
                'result' => $exam['result'],
                'unit' => $exam['unit'],
                'normal_values' => $exam['normal_values'],
            ]);
        }
        return JsonResponser::send(false, 'successfully created.',  200);
    }

    public function radiology_examination_get()
    {

        $radiology  =  Radiology_lab_patient::where('Radiology_lab_patient_examination')->latest();
        return JsonResponser::send(false, 'Billing records retrieved successfully.', $radiology, 200);
    }
}
