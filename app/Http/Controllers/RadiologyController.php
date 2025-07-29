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
use App\Http\Requests\RadiologyResultRequest;
use App\Models\Radiology_lab_patient_examination;
use App\Models\RadiologyResult;
use Illuminate\Support\Facades\DB;
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


        $radiology =  Radiology::with([
            'consultation.patientVisit.billingLogsForPatient',
            'consulted_by',
            'consultation.patient'
        ])->get();
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

    public function result(RadiologyResultRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->radiologyService->result($request);

            DB::commit();
            return JsonResponser::send(false, 'Result update successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateResult($id, RadiologyResultRequest $request)
    {
        try {
            DB::beginTransaction();
            $result = RadiologyResult::find($id);
            if (!$result) {
                return JsonResponser::send(true, 'Radiology result not found.', [], 404);
            }
            $record = $this->radiologyService->updateResult($request, $id);

            DB::commit();
            return JsonResponser::send(false, 'Result updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function radiology_examination(Radiology_examination_request $request)
    {
        // Radiology_lab_patient
        $validated  = $request->validated();

        $radiology = Radiology_lab_patient::create([
            'patient_id' =>  $validated['patient_id'],
            'patient_visits_id' => $validated['patient_visits_id'],
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
            // dd($exam);
            $Radiology_lab_patient_examination = new Radiology_lab_patient_examination();

            //      'examination',
            // 'result',
            // 'unit',
            // 'normal_values',
            $Radiology_lab_patient_examination->radiology_lab_patients_id = $radiology->id;
            $Radiology_lab_patient_examination->examination = $exam['examination'];
            $Radiology_lab_patient_examination->result = $exam['result'];
            $Radiology_lab_patient_examination->unit = $exam['unit'];
            $Radiology_lab_patient_examination->normal_values = $exam['normal_values'];
            $Radiology_lab_patient_examination->save();
        }
        return JsonResponser::send(false, 'successfully created.',  200);
    }

    public function radiology_examination_get(Request $request)
    {
        $validated =  $request->validate([
            'patient_visits_id' => "nullable|numeric"
        ]);

        $radiology  =  Radiology_lab_patient::with('examinations')->where('patient_visits_id', intval($validated['patient_visits_id']))->first();

        return JsonResponser::send(false, 'retrieved successfully.', $radiology, 200);
    }
}
