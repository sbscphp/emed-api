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

    public function show(Request $request)
    {
        try {

            DB::connection('tenant');

            $patientVisit = PatientVisit::where('visitno', $request->visit_no)->first();

            if (!$patientVisit) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $patient = Patient::find($patientVisit->patient_id);

            $labTestQuery = Laboratory::query()
                ->where('patient_id', $patientVisit->patient_id)
                ->where('visitno', $request->visit_no)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('test_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('lab_dept', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('ordered_test', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('others', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereRelation('billingLogs', 'payment_status', $request->payment_status);
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('test_status', $request->test_status);
                })
                ->with('billingLogs')
                ->orderBy('id', 'DESC');

            $labTest = $request->paginate === "true"
                ? $labTestQuery->paginate($request->limit ?? 10)
                : $labTestQuery->get();

            if ($request->export) {
                // Always work with a collection for exports
                $exportData = $labTestQuery->get()->map(function ($item) {
                    return [
                        'Type Of Test'   => $item->test_name,
                        'Price'          => $item->billingLogs->grand_total ?? 0,
                        'Mode Of Payment' => $item->billingLogs->payment_method ?? 'N/A',
                        'Date'           => $item->created_at->toDateTimeString(),
                        'Payment Status' => $item->billingLogs->payment_status ?? 'N/A',
                        'Test Status'    => $item->test_status,
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'lab-records.csv');
                }

                if ($request->export === 'pdf') {
                    $pdf = PDF::loadView('exports.patients', ['patients' => $exportData->toArray()])
                        ->setPaper('A1', 'landscape');
                    return $pdf->download('lab-records.pdf');
                }
            }

            $data = [
                "patient" => $patient,
                'patientVisit' => $patientVisit,
                'labTest' => $labTest,
            ];

            return JsonResponser::send(false, 'Record(s) found successfully.', $data, 200);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function patientDetails($id)
    {
        try {
            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $patientDetails = Patient::find($id);

            if (is_null($patientDetails)) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $data = $patientDetails->load(['nextOfKin', 'emergencyContact', 'visits.billingLogsForPatient', 'service']);


            $serviceDate = $patientDetails->service->name ?? null;
            $servceid =  $patientDetails->service->id ?? null;

            $data->visits->transform(function ($visit) use ($serviceDate,  $servceid) {
                $visit->service_name = $serviceDate;
                $visit->service_id = $servceid;
                return $visit;
            });
            unset($data->service);

            return JsonResponser::send(false, 'Record retrieved successfully.', collect($data), 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function patientVisitSummary($id)
    {
        try {

            $patientVisit = PatientVisit::find($id);
            $patient = Patient::with('service', 'triage', 'familyHistory', 'medicalHistory', 'socialHistory', 'drugHistory')->find($patientVisit->patient_id);
            $consultation_Details =  Consultation::where('visitno',  $patientVisit->visitno)->first();
            $laboratoryDetail = Laboratory::where('visitno',  $patientVisit->visitno)->first();
            $radiologyDetail = Radiology::where('visitno',  $patientVisit->visitno)->first();
            $treatmentDetail = Treatment::where('visitno',  $patientVisit->visitno)->orderBy('id', 'DESC')->get();
            $billingLog = BillingLog::where('visit_id',  $patientVisit->id)->first();
            $data = [
                "patient" => $patient,
                "patientVisit" => $patientVisit,
                "consultation" => $consultation_Details,
                "laboratory" => $laboratoryDetail,
                "radiology" => $radiologyDetail,
                "billingLog" => $billingLog,
                "treatment" => $treatmentDetail,
            ];
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function updateTest(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $test = Laboratory::find($id);
            if (!$test) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }
            $record = $this->laboratoryService->updateTest($request, $test);

            DB::commit();
            return JsonResponser::send(false, 'Result updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateResult(Request $request)
    {
        try {
            DB::beginTransaction();
            $test = Laboratory::find($request->test_id);
            if (!$test) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }
            $record = $this->laboratoryService->updateResult($request, $test);

            DB::commit();
            return JsonResponser::send(false, 'Result updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
