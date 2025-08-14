<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CounsellorDetailRequest;
use App\Http\Requests\ObservationRequest;
use App\Models\BillingLog;
use App\Models\CounsellingDetail;
use App\Models\ObservationRecommendation;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Responser\JsonResponser;
use App\Services\HivAids\HivAidsService;
use Illuminate\Http\Request;

class HivAidsController extends Controller
{
    protected HivAidsService $hivAidsService;

    public function __construct(
        HivAidsService $hivAidsService,
    ) {
        $this->hivAidsService = $hivAidsService;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function createCouncellingDetails(CounsellorDetailRequest $request)
    {
        try {
            $record = $this->hivAidsService->createCouncellingDetails($request);

            return JsonResponser::send(false, 'Record(s) create successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createObservation(ObservationRequest $request)
    {
        try {
            $record = $this->hivAidsService->createObservation($request);

            return JsonResponser::send(false, 'Record(s) create successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function summary(Request  $request, $id)
    {
        try {
            $patientVisit = PatientVisit::find($id);
            $patient = Patient::with('service', 'triage', 'familyHistory', 'medicalHistory', 'socialHistory', 'drugHistory')->find($patientVisit->patient_id);
            $counsellingDetail = CounsellingDetail::where('visit_id', $id)->first();
            $observation = ObservationRecommendation::where('visit_id', $id)->first();
            $billingLog = BillingLog::where('visit_id',  $patientVisit->id)->first();
            $data = [
                "patient" => $patient,
                "patientVisit" => $patientVisit,
                "counsellingDetail" => $counsellingDetail,
                "observation" => $observation,
                "billingLog" => $billingLog,
            ];
            return JsonResponser::send(false, 'Record fetch successfully', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }
}
