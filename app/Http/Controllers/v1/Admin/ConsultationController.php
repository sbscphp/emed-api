<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConsultationRequest;
use App\Responser\JsonResponser;
use App\Services\Consultation\ConsultationService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsultationController extends Controller
{
    protected $userService;
    protected $patientService;
    protected $patientVisitService;
    protected $consultationService;
    public function __construct(
        UserService $userService,
        PatientService $patientService,
        PatientVisitService $patientVisitService,
        ConsultationService $consultationService
    )
    {
        $this->userService = $userService;
        $this->patientService = $patientService;
        $this->patientVisitService = $patientVisitService;
        $this->consultationService = $consultationService;
    }

    public function patientsForConsultation()
    {
        try {

            DB::connection('tenant');
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patients = $this->consultationService->getPatients();
            if($patients->isEmpty()){
                return JsonResponser::send(true, 'Records not found.', null, 404);
            }
            $patients->load(['patient']);

            return JsonResponser::send(false, 'Records found successfully.', $patients, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', null, 500, $th);
        }
    }

    public function storeConsultationInfo(ConsultationRequest $request, $visitno)
    {
        try{
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patient = $this->patientVisitService->findByAttribute('visitno',$visitno);
            if(!$patient){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if($request->follow_up === 1 && $request->followUp_date === null){
                return JsonResponser::send(true, 'Please kindly provide a date for follow up', null, 422);
            }

            if($request->referral === 1 && $request->referral_details === null){
                return JsonResponser::send(true, 'Please kindly provide details of the referral', null, 422);
            }

            $data = [
                'patient_id' => $patient->patient_id,
                'admin_id' => $user->id,
                'visitno' => $patient->visitno,
                'complaint' => $request->complaint,
                'complaint_history' => $request->complain_history,
                'review' => $request->review,
                'diagnosis' => $request->diagnosis,
                'allergy' => $request->allergy,
                'disease_pattern' => $request->disease_pattern,
                'disease_types' => $request->disease_types,
                'investigation ' => $request->investigation,
                'referral' => $request->referral,
                'referral_details' => $request->referral_details,
                'admiited' => $request->admitted
            ];

            $consultation = $this->consultationService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $consultation->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details created successfully', ['consultation'=> $consultation], 201);
        }catch(\Throwable $th){

        }
    }

    public function storeConsultationLabInfo(ConsultationRequest $request, $visitno)
    {
        try{
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patient = $this->patientVisitService->findByAttribute('visitno',$visitno);
            if(!$patient){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if($request->follow_up === 1 && $request->followUp_date === null){
                return JsonResponser::send(true, 'Please kindly provide a date for follow up', null, 422);
            }

            if($request->referral === 1 && $request->referral_details === null){
                return JsonResponser::send(true, 'Please kindly provide details of the referral', null, 422);
            }

            $data = [
                'patient_id' => $patient->patient_id,
                'admin_id' => $user->id,
                'visitno' => $patient->visitno,
                'complaint' => $request->complaint,
                'complaint_history' => $request->complain_history,
                'review' => $request->review,
                'diagnosis' => $request->diagnosis,
                'allergy' => $request->allergy,
                'disease_pattern' => $request->disease_pattern,
                'disease_types' => $request->disease_types,
                'investigation ' => $request->investigation,
                'referral' => $request->referral,
                'referral_details' => $request->referral_details,
                'admiited' => $request->admitted
            ];

            $consultation = $this->consultationService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $consultation->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details created successfully', ['consultation'=> $consultation], 201);
        }catch(\Throwable $th){

        }
    }

    public function storeConsultationRadiologyInfo(ConsultationRequest $request, $visitno)
    {
        try{
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patient = $this->patientVisitService->findByAttribute('visitno',$visitno);
            if(!$patient){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if($request->follow_up === 1 && $request->followUp_date === null){
                return JsonResponser::send(true, 'Please kindly provide a date for follow up', null, 422);
            }

            if($request->referral === 1 && $request->referral_details === null){
                return JsonResponser::send(true, 'Please kindly provide details of the referral', null, 422);
            }

            $data = [
                'patient_id' => $patient->patient_id,
                'admin_id' => $user->id,
                'visitno' => $patient->visitno,
                'complaint' => $request->complaint,
                'complaint_history' => $request->complain_history,
                'review' => $request->review,
                'diagnosis' => $request->diagnosis,
                'allergy' => $request->allergy,
                'disease_pattern' => $request->disease_pattern,
                'disease_types' => $request->disease_types,
                'investigation ' => $request->investigation,
                'referral' => $request->referral,
                'referral_details' => $request->referral_details,
                'admiited' => $request->admitted
            ];

            $consultation = $this->consultationService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $consultation->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details created successfully', ['consultation'=> $consultation], 201);
        }catch(\Throwable $th){

        }
    }

    public function storeConsultationTreatmentInfo(ConsultationRequest $request, $visitno)
    {
        try{
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patient = $this->patientVisitService->findByAttribute('visitno',$visitno);
            if(!$patient){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if($request->follow_up === 1 && $request->followUp_date === null){
                return JsonResponser::send(true, 'Please kindly provide a date for follow up', null, 422);
            }

            if($request->referral === 1 && $request->referral_details === null){
                return JsonResponser::send(true, 'Please kindly provide details of the referral', null, 422);
            }

            $data = [
                'patient_id' => $patient->patient_id,
                'admin_id' => $user->id,
                'visitno' => $patient->visitno,
                'complaint' => $request->complaint,
                'complaint_history' => $request->complain_history,
                'review' => $request->review,
                'diagnosis' => $request->diagnosis,
                'allergy' => $request->allergy,
                'disease_pattern' => $request->disease_pattern,
                'disease_types' => $request->disease_types,
                'investigation ' => $request->investigation,
                'referral' => $request->referral,
                'referral_details' => $request->referral_details,
                'admiited' => $request->admitted
            ];

            $consultation = $this->consultationService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $consultation->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details created successfully', ['consultation'=> $consultation], 201);
        }catch(\Throwable $th){

        }
    }
}
