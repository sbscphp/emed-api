<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\Consultation_Details_Treatment;
use App\Models\FamilyHistory;
use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use App\Models\SocialHistory;
use App\Models\Treatment;
use App\Responser\JsonResponser;
use App\Services\Consultation\ConsultationService;
use App\Services\Laboratory\LaboratoryService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

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
    ) {
        $this->userService = $userService;
        $this->patientService = $patientService;
        $this->patientVisitService = $patientVisitService;
        $this->consultationService = $consultationService;
        $this->laboratoryService = $laboratoryService;
    }

    public function allLabRecords(Request $request)
    {
        try {
            $search = $request->search;
            $status = $request->status;
            $paymentStatus = $request->payment_status;
            $paginate = $request->paginate ?? false;
            $perPage = $request->perPage;
            $from = $request->from;
            $to  = $request->to;
            $export = $request->export;
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }


            $labRecords = $this->laboratoryService->getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage, $export, $from, $to);
            $laboratory = Laboratory::query()
                ->with('consultation.pharmacist')
                ->join('patients', 'patient_visit_lab.patient_id', '=', 'patients.id')
                ->leftJoin('billing_logs', 'patient_visit_lab.patient_id', '=', 'billing_logs.patient_id')
                ->select(
                    'patient_visit_lab.*',
                    'patients.firstname',
                    'patients.lastname',
                    'patients.patientno',
                    'patients.cardno',
                    'billing_logs.id as billing_id',
                    'billing_logs.sub_total as billing_amount',
                    'billing_logs.payment_status as billing_status'
                )->get();

            if (!$paginate  && $laboratory->count() == 0) {

                return JsonResponser::send(false, "Record(s) not found.", $labRecords, 200);
            }

            if (!empty($export)) {

                $exportData = $laboratory->map(function ($item) {
                    return [
                        'Patient Name' => "{$item->firstname} {$item->lastname}",
                        'Patient No' => $item->patientno,
                        'Consulted By' => $item->consultation->pharmacist->role,
                        'Card No' => $item->cardno,
                        'Visit No' => $item->visitno,
                        'Lab Dept' => $item->lab_dept,
                        'Test Name' => $item->test_name,
                        'Ordered Tests' => $item->ordered_test,
                        'Others' => $item->others,
                        'Test Status' => $item->test_status,
                        'Payment Status' => $item->payment_status,
                        'Billing Amount' => $item->billing_amount,
                        'Billing Status' => $item->billing_status,
                        'Created At' => $item->created_at->toDateTimeString(),
                    ];
                });

                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'lab-records.csv');
                }

                if ($export === 'pdf') {
                    // return ExportHelper::downloadPdf($exportData->toArray(), 'lab-records.pdf');

                    $pdf = PDF::loadView('exports.patients', ['patients' => $exportData->toArray()])->setPaper('A1', 'landscape');
                    return $pdf->download('lab-records.pdf');
                }
            }




            $response = [
                'records' => $labRecords,
                'total' => collect($labRecords)->count()
            ];
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Record(s) found successfully.', $response, 200);
        } catch (Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function stats()
    {
        try {

            DB::connection('tenant');
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $stats = $this->laboratoryService->getStats();

            return JsonResponser::send(false, 'Record(s) found successfully.', $stats, 200);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function show(Request $request, $visitNo)
    {
        try {

            DB::connection('tenant');

            $patientVisit = PatientVisit::where('visitno', $visitNo)->first();

            if (!$patientVisit) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $patient = $patientVisit ? Patient::find($patientVisit->patient_id) : null;
            $treatment = Consultation_Details_Treatment::query()
                ->where('patient_id', $patientVisit->patient_id)->where('patient_visits_id', $patientVisit->id)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('title', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->is_read, function ($query) use ($request) {
                    $query->where('is_read', $request->is_read);
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('test_status', $request->test_status);
                });

            if ($request->paginate === true) {
                $treatment->orderBy('id', 'DESC')->paginate($request->limit);
            }

            $treatment->orderBy('id', 'DESC')->get();

            $data = [
                "patient" => $patient,
                'patientVisit' => $patientVisit,
                'treatment' => $treatment,

                // 'consultation' => $consultation,
                // 'radiology' => $radiology,
                // 'treatment' => $treatment,
                // 'billing' => $billingLogsForPatient,
                // 'service' => $service,
                // 'serviceunit' => $serviceunit,
                // "laboratory" => $record,
                // "socialhistory" => $socalhistory,
                // "familyHistory" => $familyHistory
            ];

            return JsonResponser::send(false, 'Record(s) found successfully.', $data, 200);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
