<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\LabService;
use App\Models\Medication;
use App\Models\RadiologyService;
use App\Responser\JsonResponser;

class GeneralController extends Controller
{
    public function allLabTest()
    {
        try {
            $record = LabService::orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allRadiologyTest()
    {
        try {
            $record = RadiologyService::orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allMedicine()
    {
        try {
            $record = Medication::orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
