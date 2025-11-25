<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\GeneralEnums;
use App\Helpers\ExportHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation__Details__Laborartories_Request;
use App\Http\Requests\Consultation__DetailsRequest;
use App\Http\Requests\Consultation_Detail_Radiology_Request;
use App\Http\Requests\Consultation_Details_Treatment_Request;
use App\Http\Requests\ConsultationLaborartoryRequest;
use App\Http\Requests\CounsellorDetailRequest;
use App\Http\Requests\CreateservichospitalRequst;
use App\Http\Requests\DosageAdminRequest;
use App\Http\Requests\EditservichospitalRequest;
use App\Http\Requests\ImmunizationRequest;
use App\Http\Requests\Observetation_Recommandation_Request;
use App\Models\BillingLog;
use App\Models\Consultation_Details;
use App\Models\Consultation_Details_Laborartory;
use App\Models\Consultation_Details_Radiology;
use App\Models\Consultation_Details_Treatment;
use App\Models\CounsellingDetail;
use App\Models\DosageAdministration;
use App\Models\Immunization;
use App\Models\ObservationRecommendation;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use App\Responser\JsonResponser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImmunizationController extends Controller
{


    public function create_immunization(ImmunizationRequest $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $validated = $request->validated();
            $data = Immunization::updateOrCreate(
                [
                    'visit_id' => $validated['visit_id'],
                    'tenant_id' => $tenantId,
                ], // Unique key
                $validated // Data to update/create
            );

            $visit = PatientVisit::find($data['visit_id']);
            $visit->update([
                'immunization_status' => GeneralEnums::COMPLETED->value,
            ]);
            return JsonResponser::send(false, ' Create successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }

    public function dosage_admin(DosageAdminRequest  $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $validated = $request->validated();
            $data = DosageAdministration::updateOrCreate(
                [
                    'visit_id' => $validated['visit_id'],
                    'tenant_id' => $tenantId,
                ], // Unique key
                $validated // Data to update/create
            );
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }

    public function summary(Request  $request, $id)
    {
        try {
            $patientVisit = PatientVisit::find($id);
            $patient = Patient::with('service', 'triage', 'familyHistory', 'medicalHistory', 'socialHistory')->find($patientVisit->patient_id);
            $immunization = Immunization::where('visit_id', $id)->first();
            $dosageAdministration = DosageAdministration::where('visit_id', $id)->first();
            $billingLog = BillingLog::where('visit_id',  $patientVisit->id)->first();
            $data = [
                "patient" => $patient,
                "patientVisit" => $patientVisit,
                "immunization" => $immunization,
                "dosageAdministration" => $dosageAdministration,
                "billingLog" => $billingLog,
            ];
            return JsonResponser::send(false, 'Record fetch successfully', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }

    public function consultation_details(Consultation__DetailsRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = Consultation_Details::updateOrCreate(
                ['patient_visits_id' => $validated['patient_visits_id']], // unique key to match on
                $validated // data to update or insert
            );
            $patient =  Patient::find($validated['patient_id']);
            $status =    $validated['admit_patient'] == 1 ? 'admitted' : null;
            if ($patient) {
                $patient->update([
                    "status" => $status
                ]);
            }
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }


    public function consultation_details_get(Request $request)
    {
        try {
            $validated = $request->validate([
                "patient_id" => "nullable|numeric|exists:tenant.patients,id"
            ]);

            $consultation_Details =  Consultation_Details::where('patient_id',  $validated['patient_id'])->first();
            $laboratoryDetail = Consultation_Details_Laborartory::where('patient_id',  $validated['patient_id'])->first();
            $radiologyDetail = Consultation_Details_Radiology::where('patient_id',  $validated['patient_id'])->first();
            $treatmentDetail = Consultation_Details_Treatment::where('patient_id',  $validated['patient_id'])->orderBy('id', 'DESC')->get();
            $patient = Patient::find($validated['patient_id']);
            $data = [
                "consultation" => $consultation_Details,
                "laboratory" => $laboratoryDetail,
                "radiology" => $radiologyDetail,
                "treatment" => $treatmentDetail,
                "patient" => $patient
            ];
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function consultation_details_laborartory_get(Request $request)
    {
        try {
            $validated = $request->validate([
                "patient_id" => "nullable|numeric|exists:tenant.patients,id"
            ]);

            $consultation_details_laborartory =  Consultation_Details_Laborartory::where('patient_id',  $validated['patient_id'])->first();
            $patient = Patient::find($validated['patient_id']);
            $data = [
                "laborartory" => $consultation_details_laborartory,
                "patient" => $patient
            ];
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function consultation_detail_radiology_get(Request $request)
    {
        try {
            $validated = $request->validate([
                "patient_id" => "nullable|numeric|exists:tenant.patients,id"
            ]);

            $consultation_details_laborartory =  Consultation_Details_Radiology::where('patient_id',  $validated['patient_id'])->first();
            $patient = Patient::find($validated['patient_id']);
            $data = [
                "radiology" => $consultation_details_laborartory,
                "patient" => $patient
            ];
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function consultation_detail_treatment_get(Request $request)
    {
        try {
            $validated = $request->validate([
                "patient_id" => "nullable|numeric|exists:tenant.patients,id"
            ]);

            $consultation_details_treatment =  Consultation_Details_Treatment::where('patient_id',  $validated['patient_id'])->get();
            $patient = Patient::find($validated['patient_id']);
            $data = [
                "treatment" => $consultation_details_treatment,
                "patient" => $patient
            ];
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function  consultation_details_laborartory(ConsultationLaborartoryRequest $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details_Laborartory::updateOrCreate(
                ['patient_visits_id' => $validated['patient_visits_id']], // unique key to match on
                $validated // data to update or insert
            );
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error   .', [], 500, $th);
        }
    }

    public function consultation_detail_radiology(Consultation_Detail_Radiology_Request $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details_Radiology::updateOrCreate(
                ['patient_visits_id' => $validated['patient_visits_id']], // unique key to match on
                $validated // data to update or insert
            );
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error   .', [], 500, $th);
        }
    }

    public function consultation_detail_treatment(Consultation_Details_Treatment_Request $request)
    {
        try {
            $validated = $request->validated();
            //   Consultation_Details_Treatment_Request $request

            // Loop through each treatment and create it
            foreach ($request['treatments'] as $treatment) {
                Consultation_Details_Treatment::create($treatment);
            }

            return JsonResponser::send(false, ' created successfully.', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function create_service(CreateservichospitalRequst $request)
    {
        try {
            $validated = $request->validated();
            $tenantId = $request->header('X-Tenant-ID');
            $serviceunit = ServiceUnit::where("name", "Radiology")->first() ?? null;
            $data = Service::create([
                'tenant_id'        => $tenantId,
                "service_unit_id" => $serviceunit->id,
                "name" => $validated['name'],
                "price" => $validated['price']
            ]);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }

    public function edit_service(EditservichospitalRequest $request)
    {
        try {
            $validated = $request->validated();
            $service = Service::find($validated['id']);
            if ($service) {
                $service->update($validated);
                return JsonResponser::send(false, 'edit successfully.', $service);
            }
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }

    public function service(Request $request)
    {
        try {
            $validated = $request->validate([
                "search" => "nullable|string",
                "export" => "nullable|string|in:pdf,csv"
            ]);

            if (!empty($validated['export'])) {
                $exportData = Service::all()->toArray();

                if ($validated['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'service.csv');
                }

                if ($validated['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'service.pdf');
                }
            }
            $tenantId = $request->header('X-Tenant-ID');

            $services = Service::where('tenant_id', $tenantId)
                ->when(!empty($validated['search']), function ($query) use ($validated) {
                    $search = $validated['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('price', 'LIKE', "%{$search}%");
                    });
                })->paginate(10);
            return JsonResponser::send(false, 'featch successfully.', $services);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }


    public function observetation_recommandation(CounsellorDetailRequest $request)
    {
        try {
            $validated = $request->validated();
            $data = CounsellingDetail::create($validated);
            return JsonResponser::send(false, 'successfull.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }
}
