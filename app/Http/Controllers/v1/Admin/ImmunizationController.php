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
use App\Models\Consultation_Details;
use App\Models\Consultation_Details_Laborartory;
use App\Models\Consultation_Details_Radiology;
use App\Models\Consultation_Details_Treatment;
use App\Models\Dosage_Adminstration;
use App\Models\Immunization;
use App\Models\ServiceDepartment;
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
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function consultation_detail_radiology(Consultation_Detail_Radiology_Request $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details_Radiology::create($validated);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function consultation_detail_treatment(Consultation_Details_Treatment_Request $request)
    {
        try {
            $validated = $request->validated();
            $data = Consultation_Details_Treatment::create($validated);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $th);
        }
    }

    public function create_service(CreateservichospitalRequst $request)
    {
        try {
            $validated = $request->validated();
            $data = ServiceDepartment::create($validated);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }

    public function edit_service(EditservichospitalRequest $request)
    {
        try {
            $validated = $request->validated();
            $service = ServiceDepartment::find($validated['id']);
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
                $exportData = ServiceDepartment::all()->toArray();

                if ($validated['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'service.csv');
                }

                if ($validated['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'service.pdf');
                }
            }

            $services = ServiceDepartment::when(!empty($validated['search']), function ($query) use ($validated) {
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
}
