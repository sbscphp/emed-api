<?php

namespace App\Http\Controllers\v1\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation__Details__Laborartories_Request;
use App\Http\Requests\Consultation__DetailsRequest;
use App\Http\Requests\Consultation_Detail_Radiology_Request;
use App\Http\Requests\Consultation_Details_Treatment_Request;
use App\Http\Requests\DosageAdminRequest;
use App\Http\Requests\ImmunizationRequest;
use App\Models\Consultation_Details;
use App\Models\Consultation_Details_Laborartory;
use App\Models\Consultation_Details_Radiology;
use App\Models\Consultation_Details_Treatment;
use App\Models\Dosage_Adminstration;
use App\Models\Immunization;
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
}
