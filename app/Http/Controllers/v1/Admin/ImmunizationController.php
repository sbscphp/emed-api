<?php

namespace App\Http\Controllers\v1\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\DosageAdminRequest;
use App\Http\Requests\ImmunizationRequest;
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
            return JsonResponser::send(false, ' fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching  .', [], 500, $e);
        }
    }
}
