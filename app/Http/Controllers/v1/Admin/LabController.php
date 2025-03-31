<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Consultation\ConsultationService;
use App\Services\Laboratory\LaboratoryService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;
use function PHPUnit\Framework\isEmpty;

class LabController extends Controller
{
    protected $userService;
    protected $patientService;
    protected $patientVisitService;
    protected $consultationService;
    protected $laboratoryService;

    public function __construct(
        UserService $userService,
        PatientService $patientService,
        PatientVisitService $patientVisitService,
        ConsultationService $consultationService,
        LaboratoryService $laboratoryService,
    )
    {
        $this->userService = $userService;
        $this->patientService = $patientService;
        $this->patientVisitService = $patientVisitService;
        $this->consultationService = $consultationService;
        $this->laboratoryService = $laboratoryService;
    }

    public function allLabRecords(Request $request)
    {
        try{
            $search = $request->search;
            $status = $request->status;
            $paymentStatus = $request->payment_status;
            $paginate = $request->paginate ?? false;
            $perPage = $request->perPage;

            DB::connection('tenant');
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $labRecords = $this->laboratoryService->getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage);

            if($labRecords->isEmpty()){
                return JsonResponser::send(true, 'Record(s) not found.', null, 404);
            }

            $response = [
                'records' => $labRecords,
                'total' => $labRecords->count()
            ];

            return JsonResponser::send(false, 'Record(s) found successfully.', $response, 200);
        }catch(Throwable $th){
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function stats()
    {
        try{

            DB::connection('tenant');
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $stats = $this->laboratoryService->getStats();

            return JsonResponser::send(false, 'Record(s) found successfully.', $stats, 200);
        }catch(Throwable $th){
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function show($visitNo)
    {
        try{

            DB::connection('tenant');
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if(!$user){
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $record = $this->laboratoryService->findByAttribute('visitno',$visitNo);
            if(!$record){
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            return JsonResponser::send(false, 'Record(s) found successfully.', $record, 200);
        }catch(Throwable $th){
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}

