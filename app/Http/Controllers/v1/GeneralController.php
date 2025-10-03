<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\LabService;
use App\Models\PharmacyRequest;
use App\Models\RadiologyService;
use App\Models\Service;
use App\Models\ServiceUnit;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;

class GeneralController extends Controller
{
    public function allLabTest(Request $request)
    {
        try {
            $record = LabService::when(!empty($request->type), function ($query) use ($request) {
                $query->where('type', $request->type);
            })->orderBy('id', 'DESC')->get();

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
            $record = PharmacyRequest::with('pharmacy')->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allService()
    {
        try {
            $record = Service::orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allServiceUnits()
    {
        try {
            
            $record = ServiceUnit::orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
