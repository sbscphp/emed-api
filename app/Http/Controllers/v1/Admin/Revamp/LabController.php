<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Enums\GeneralEnums;
use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\Treatment;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\Revamp\LaboratoryService;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;

class LabController extends Controller
{
    protected LaboratoryService $laboratoryService;

    public function __construct(
        LaboratoryService $laboratoryService,
    ) {
        $this->laboratoryService = $laboratoryService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->laboratoryService->overview($request);

            $stats = $this->laboratoryService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->laboratoryService->export($overview, $format);
            }

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allTests(Request $request)
    {
        try {
            DB::connection('tenant');
            $tenantId = $request->header('X-Tenant-ID');

            $labTestQuery = Laboratory::query()
                ->with('billingLogDetail.billingLog')
                ->where('tenant_id', $tenantId)
                ->where('visit_id', $request->visit_id)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where(function ($subQuery) use ($request) {
                        $subQuery->where('test_name', 'LIKE', '%' . $request->search_param . '%')
                            ->orWhere('department', 'LIKE', '%' . $request->search_param . '%')
                            ->orWhere('status', 'LIKE', '%' . $request->search_param . '%')
                            ->orWhereHas('consultation.consultedDoctor', function ($doctorQuery) use ($request) {
                                $doctorQuery->where('fullname', 'LIKE', $request->search_param)
                                    ->orWhere('email', 'LIKE', $request->search_param);
                            });
                        // ->orWhere('ordered_test', 'LIKE', '%' . $request->search_param . '%')
                        // ->orWhere('others', 'LIKE', '%' . $request->search_param . '%');

                    });
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereRelation('billingLogDetail', 'status', $request->payment_status);
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('status', $request->test_status);
                })
                ->with(['patient', 'visit', 'consultation:id,consulted_by', 'billingLogDetail'])
                ->orderBy('id', 'DESC');

            // Fetch data
            $labTest = $request->paginate === "true"
                ? $labTestQuery->paginate($request->limit ?? 10)
                : $labTestQuery->get();

            // Attach consulted user from landlord DB
            $labTest->each(function ($item) {
                if ($item->consultation && $item->consultation->consulted_by) {
                    $consultedUser = User::on('landlord')
                        ->select('id', 'first_name', 'last_name', 'email')
                        ->find($item->consultation->consulted_by);

                    $item->consultedBy = $consultedUser;
                } else {
                    $item->consultedBy = null;
                }
            });

            // Handle export
            if ($request->export) {
                // Use the already hydrated collection with consultedBy info
                $exportData = $labTest->map(function ($item) {
                    return [
                        'Date'           => $item->created_at->toDateTimeString(),
                        'Consulted By'   => optional($item->consultedBy)->first_name . ' ' . optional($item->consultedBy)->last_name ?? 'N/A',
                        'Type Of Test'   => $item->test_name,
                        'Price'          => $item->billingLogDetail->amount ?? 0,
                        'Payment Status' => $item->billingLogDetail->status ?? 'N/A',
                        'Test Status'    => $item->status,
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

            return JsonResponser::send(false, 'Record(s) found successfully.', $labTest, 200);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {
            DB::connection('tenant');

            $record = Laboratory::with(['results', 'patient', 'visit', 'consultation:id,consulted_by', 'billingLogDetail'])->find($id);
            if (!$record) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            // Manually fetch dispensed user from landlord DB
            if ($record->consultation->consulted_by) {
                $consultedUser = User::on('landlord')
                    ->select('id', 'fullname', 'email')
                    ->find($record->consultation->consulted_by);

                $record->setAttribute('consultedBy', $consultedUser);
            } else {
                $record->setAttribute('consultedBy', null);
            }

            if ($record->user_id) {
                $labUsers = User::on('landlord')
                    ->select('id', 'fullname', 'email')
                    ->find($record->user_id);

                $record->setAttribute('attendedBy', $labUsers);
            } else {
                $record->setAttribute('attendedBy', null);
            }

            return JsonResponser::send(false, 'Record(s) found successfully.', $record, 200);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function updateResult(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $test = Laboratory::with(['billingLogDetail'])->find($id);
            if (!$test) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            if (!$test->billingLogDetail || $test->billingLogDetail->status !== GeneralEnums::PAID->value) {
                return JsonResponser::send(true, 'Please make payment.', [], 402);
            }
            $record = $this->laboratoryService->updateResult($request, $test);

            DB::commit();
            return JsonResponser::send(false, 'Result updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateTestStatus(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $test = Laboratory::find($id);
            if (!$test) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            if (!$test->billingLogDetail || $test->billingLogDetail->status !== GeneralEnums::PAID->value) {
                return JsonResponser::send(true, 'Please make payment.', [], 402);
            }

            $record = $this->laboratoryService->updateTest($request, $test);

            DB::commit();
            return JsonResponser::send(false, 'Result updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function patientVisitSummary(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $patientVisit = PatientVisit::where('tenant_id', $tenantId)->where('id', $id)->first();
            $patient = Patient::where('tenant_id', $tenantId)->with('service', 'triage', 'familyHistory', 'medicalHistory', 'socialHistory')->find($patientVisit->patient_id);
            $consultation_Details =  Consultation::where('tenant_id', $tenantId)->where('visit_id',  $patientVisit->visit_id)->first();
            $laboratoryDetail = Laboratory::where('tenant_id', $tenantId)->where('visit_id',  $patientVisit->visit_id)->first();
            $radiologyDetail = Radiology::where('tenant_id', $tenantId)->where('visit_id',  $patientVisit->visit_id)->first();
            $treatmentDetail = Treatment::where('tenant_id', $tenantId)->where('visit_id',  $patientVisit->visit_id)->orderBy('id', 'DESC')->get();
            $billingLog = BillingLog::where('tenant_id', $tenantId)->where('visit_id',  $patientVisit->id)->first();
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
}
