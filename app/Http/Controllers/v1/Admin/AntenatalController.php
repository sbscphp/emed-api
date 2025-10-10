<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AntenatalLabTestRequest;
use App\Http\Requests\Admin\AntenatalRequest;
use App\Http\Requests\Admin\DeliveryDetailsRequest;
use App\Http\Requests\Admin\NewBornDetailsRequest;
use App\Models\Antenatal;
use App\Models\AntenatalLabTest;
use App\Models\BillingLog;
use App\Models\DeliveryDetail;
use App\Models\NewBornDetail;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Responser\JsonResponser;
use App\Services\Antenatal\AntenatalService;
use Illuminate\Http\Request;
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
            DB::beginTransaction();
            $record = $this->antenatalService->createAntenatalLabTest($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'Antenatal lab test record created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
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
            $patient = Patient::with('service', 'triage', 'familyHistory', 'medicalHistory', 'socialHistory', 'drugHistory')->find($patientVisit->patient_id);
            $antenatalDetail = Antenatal::where('visit_id', $id)->first();
            // $antenatalLabTest = AntenatalLabTest::where('visit_id', $id)->first();
            $deliveryDetail = DeliveryDetail::where('visit_id',  $patientVisit->id)->first();
            $newBornDetails = NewBornDetail::where('visit_id',  $patientVisit->id)->first();
            $billingLog = BillingLog::where('visit_id',  $patientVisit->id)->first();
            $data = [
                "patient" => $patient,
                "patientVisit" => $patientVisit,
                "antenatalDetail" => $antenatalDetail,
                // "antenatalLabTest" => $antenatalLabTest,
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
