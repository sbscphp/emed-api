<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResourceRecent;
use App\Models\Appointment;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\DrugHistory;
use App\Models\FamilyHistory;
use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\Treatment;
use App\Services\ServiceDepartment\ServiceDepartmentService;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MainDashBoardStatsController extends Controller
{
    public $service_department_service;

    public function __construct(ServiceDepartmentService $service_department_service)
    {
        $this->service_department_service = $service_department_service;
    }
    public function index(Request $request)
    {

        try {
            $validated = $request->validate([
                "filter_calender" => 'nullable|string'
            ]);

            $validated['filter_calender'] ?? "daily";
            $data = $this->service_department_service->main_dashboard($validated);
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching billing stats.', [], 500, $e);
        }
    }


    public function top_drugs()
    {
        try {
            $data = $this->service_department_service->top_drugs();
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }


    public function recent_patient(Request $request)
    {
        try {
            $validated = $request->validate([
                'search' => "nullable|string",
                'patient_type' => "nullable|string",
                'status' => "nullable|string",
                'start_date' => "nullable|date",
                "end_date" => "nullable|date",
                'gender' => "nullable|string",
            ]);
            $data = $this->service_department_service->recent_patient($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource
                $patient =  Patient::with('patient_visits_latest')->get();
                if (count($patient) == 0) {
                    return JsonResponser::send(true, 'No Data.', [], 500);
                }
                $exportData = PatientResourceRecent::collection($patient)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }


    public function yearly_patient(Request $request)
    {
        try {
            $validated = $request->validate([
                'yearly' => "nullable|numeric",
            ]);

            $data =  Patient::selectRaw('MONTH(created_at) as month, COUNT(*) as total')->where('created_at', $validated['yearly'] ?? Carbon::now()->year())
                ->groupBy(DB::raw('MONTH(created_at)'))
                ->orderBy('month')
                ->get()->map(function ($item) {
                    $item->month = Carbon::create()->month($item->month)->format('M');
                    return $item;
                });

            $patientin =  Patient::where('stage', 'triage')->count();
            $patientout = Patient::where('stage', 'discharged')->count();

            $output = [
                'patientin' => $patientin,
                'patientout' => $patientout,
                "data" => $data
            ];
            return JsonResponser::send(false, ' fetched successfully.', $output);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }

    public function  patient_age_gender()
    {
        $total = Patient::count();

        $age_0_18 = Patient::whereBetween('dob', [Carbon::now()->subYears(18), Carbon::now()])->count();
        $age_19_35 = Patient::whereBetween('dob', [Carbon::now()->subYears(35), Carbon::now()->subYears(19)->subDay()])->count();
        $age_36_plus = Patient::where('dob', '<', Carbon::now()->subYears(36))->count();

        $male = Patient::whereIn('gender', ['male', 'Male', 'MALE'])->count();
        $female = Patient::whereIn('gender', ['female', 'Female', 'FEMALE'])->count();
        $results = [
            '0-18' => $total ? round(($age_0_18 / $total) * 100, 2) : 0,
            '19-35' => $total ? round(($age_19_35 / $total) * 100, 2) : 0,
            '36+' => $total ? round(($age_36_plus / $total) * 100, 2) : 0,
            'male' => $male,
            'female' => $female

        ];


        return JsonResponser::send(false, ' fetched successfully.', $results);
    }

    public function  appointment(Request $request)
    {
        try {
            $validated = $request->validate([
                'yearly' => "nullable|numeric",
            ]);

            $data =  Appointment::selectRaw('MONTH(created_at) as month, COUNT(*) as total')->where('created_at', $validated['yearly'] ?? Carbon::now()->year())
                ->groupBy(DB::raw('MONTH(created_at)'))
                ->orderBy('month')
                ->get()->map(function ($item) {
                    $item->month = Carbon::create()->month($item->month)->format('M');
                    return $item;
                });

            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }

    public function lab_test_year(Request $request)
    {
        try {
            $validated = $request->validate([
                'yearly' => "nullable|numeric",
            ]);
            $avarge_all =  Laboratory::count();
            $avarge_complete =  Laboratory::where('status', 'complete')->count();
            $total =  Laboratory::where('created_at', $validated['yearly'] ?? Carbon::now()->year())->count();
            $complete = Laboratory::where('created_at', $validated['yearly'] ?? Carbon::now()->year())->where('status', 'complete')->count();
            $progress = Laboratory::where('created_at', $validated['yearly'] ?? Carbon::now()->year())->where('status', 'in progress')->count();
            $pending = Laboratory::where('created_at', $validated['yearly'] ?? Carbon::now()->year())->where('status', 'pending')->count();
            // $total ? round(($age_0_18 / $total) * 100, 2) : 0
            $average_completion_rate = $avarge_all ? round(($avarge_complete / $avarge_all) * 100) : 0;
            $data = [
                "average_completion_rate" => $average_completion_rate,
                "total_completion" => $avarge_complete,
                "complete" => $complete,
                "progress" => $progress,
                'pending' => $pending
            ];
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }



    public function patient_consultation_summary_data(Request $request)
    {

        try {
            $validated =  $request->validate([
                'patient_visits_id' => "nullable|numeric"
            ]);

            $arr = [];

            $data = PatientVisit::find(intval($validated['patient_visits_id']));

            if ($data) {
                $consultation = Consultation::where('visitno', $data->visitno)->first();
                $radiology = Radiology::where('visitno', $data->visitno)->first();
                $treatment = $consultation ? Treatment::where('consultation_id', $consultation->id)->first() : null;
                $billingLogsForPatient = BillingLog::where('visit_id', $data->id)->first();
                $patient = Patient::find(intval($data->patient_id));
                $family_history = $consultation ? FamilyHistory::where('consultation_id',  $consultation->id)->first() : null;
                $drug_history = $consultation ? DrugHistory::where('consultation_id',  $consultation->id)->first() : null;


                $arr[] = [
                    'Patientvisit' => $data,
                    'consultation' => $consultation,
                    'radiology' => $radiology,
                    'treatment' => $treatment,
                    'billing' => $billingLogsForPatient,
                    "patient" => $patient,
                    'family_history' => $family_history,
                    "drug_history" => $drug_history
                ];
            }

            return JsonResponser::send(false, 'Billing stats fetched successfully.', $arr);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }
}
