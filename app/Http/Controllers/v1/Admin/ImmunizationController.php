<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation__Details__Laborartories_Request;
use App\Http\Requests\Consultation__DetailsRequest;
use App\Http\Requests\Consultation_Detail_Radiology_Request;
use App\Http\Requests\Consultation_Details_Treatment_Request;
use App\Http\Requests\CreateservichospitalRequst;
use App\Http\Requests\DosageAdminRequest;
use App\Http\Requests\EditservichospitalRequest;
use App\Http\Requests\ImmunizationRequest;
use App\Http\Requests\Observetation_Recommandation_Request;
use App\Models\Consultation_Details;
use App\Models\Consultation_Details_Laborartory;
use App\Models\Consultation_Details_Radiology;
use App\Models\Consultation_Details_Treatment;
use App\Models\Dosage_Adminstration;
use App\Models\Immunization;
use App\Models\Observetation_Recommandation;
use App\Models\Patient;
use App\Models\Registartion_Service;
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
            $validated = $request->validated();
            $data = Immunization::create($validated);
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }


    public function dosage_admin(DosageAdminRequest  $request)
    {

        try {
            $validated = $request->validated();
            $data = Dosage_Adminstration::create($validated);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }

    public function consultation_details(Consultation__DetailsRequest $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details::create($validated);
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
            $patient = Patient::find($validated['patient_id']);
            $data = [
                "consultation" => $consultation_Details,
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

    public function  consultation_details_laborartory(Consultation__Details__Laborartories_Request $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details_Laborartory::create($validated);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error   .', [], 500, $th);
        }
    }

    public function consultation_detail_radiology(Consultation_Detail_Radiology_Request $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details_Radiology::create($validated);
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

            foreach ($validated as $treatment) {
                $Consultation =  new Consultation_Details_Treatment();
                $Consultation->patient_id = $treatment['patient_id'];
                $Consultation->patient_visits_id =  $treatment['patient_visits_id'];
                $Consultation->select_drug = $treatment['select_drug'];
                $Consultation->qualifier = $treatment['qualifier'];
                $Consultation->dosage = $treatment['dosage'];
                $Consultation->weight = $treatment['weight'];
                $Consultation->adherence_period = $treatment['adherence_period'];
                $Consultation->duration = $treatment['duration'];
                $Consultation->route = $treatment['route'];
                $Consultation->remark = $treatment['remark'];
                $Consultation->save();
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
            $serviceunit = ServiceUnit::where("name", "Radiology")->first() ?? null;
            $data = Registartion_Service::create([
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
            $service = Registartion_Service::find($validated['id']);
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
                $exportData = Registartion_Service::all()->toArray();

                if ($validated['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'service.csv');
                }

                if ($validated['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'service.pdf');
                }
            }

            $services = Registartion_Service::when(!empty($validated['search']), function ($query) use ($validated) {
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


    public function observetation_recommandation(Observetation_Recommandation_Request $request)
    {
        try {
            $validated = $request->validated();
            $data = Observetation_Recommandation::create($validated);
            return JsonResponser::send(false, 'successfull.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }
}
