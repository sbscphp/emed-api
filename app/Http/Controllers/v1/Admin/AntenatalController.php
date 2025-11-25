<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AntenatalLabTestRequest;
use App\Http\Requests\Admin\AntenatalRequest;
use App\Http\Requests\Admin\DeliveryDetailsRequest;
use App\Http\Requests\Admin\NewBornDetailsRequest;
use App\Http\Requests\ConsultationLaborartoryRequest;
use App\Models\Antenatal;
use App\Models\AntenatalLabTest;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\DeliveryDetail;
use App\Models\Laboratory;
use App\Models\NewBornDetail;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Antenatal\AntenatalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AntenatalController extends Controller
{
    protected AntenatalService $antenatalService;

    public function __construct(
        AntenatalService $antenatalService,
    ) {
        $this->antenatalService = $antenatalService;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function createAntenatalRecord(AntenatalRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->antenatalService->createAntenatalRecord($request);

            DB::commit();

            return JsonResponser::send(false, 'Antenatal record created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createAntenatalLabTest(AntenatalLabTestRequest $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();

            $patient = Patient::find($request->patient_id);
            if (!$patient) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $visit = PatientVisit::find($request->visit_id);
            if (!$visit) {
                return JsonResponser::send(true, 'Patient visit not yet initiated.', null, 200);
            }

            $labTest = $this->antenatalService->createLabTest($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $labTest[0]->id,
                'action' => 'Create',
                'action_type' => "Models\Laboratory",
                'log_name' => "Laboratory test created successfully",
                'description' => "{$currentUser['fullname']} created lab test successfully",
                'module_accessed' => ListModuleEnums::Laboratory
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Laboratory test recorded successfully', $labTest, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function createDeliveryDetails(DeliveryDetailsRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->antenatalService->createDeliveryDetails($request);

            DB::commit();
            return JsonResponser::send(false, 'Delivery details created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createNewBorn(NewBornDetailsRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->antenatalService->createNewBorn($request);

            DB::commit();
            return JsonResponser::send(false, 'New born details created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function summary($id)
    {
        try {
            $patientVisit = PatientVisit::find($id);
            $patient = Patient::with('service', 'triage', 'familyHistory', 'medicalHistory', 'socialHistory')->find($patientVisit->patient_id);
            $antenatalDetail = Antenatal::where('visit_id', $id)->with('consultedBy')->first();
            $antenatalLabTest = Laboratory::where('visit_id', $id)->orderBy('id', 'DESC')->get();
            $deliveryDetail = DeliveryDetail::where('visit_id',  $patientVisit->id)->first();
            $newBornDetails = NewBornDetail::where('visit_id',  $patientVisit->id)->first();
            $billingLog = BillingLog::where('visit_id',  $patientVisit->id)->first();
            $consultation = Consultation::where('visit_id', $id)->with('consultedDoctor')->first();

            $data = [
                "patient" => $patient,
                "patientVisit" => $patientVisit,
                "antenatalDetail" => $antenatalDetail,
                "antenatalLabTest" => $antenatalLabTest,
                "deliveryDetail" => $deliveryDetail,
                "newBornDetails" => $newBornDetails,
                "billingLog" => $billingLog,
            ];
            return JsonResponser::send(false, 'Record fetch successfully', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }
}
