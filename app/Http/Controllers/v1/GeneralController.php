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
            $tenantId = $request->header('X-Tenant-ID');
            $record = LabService::where('tenant_id', $tenantId)
                ->when(!empty($request->type), function ($query) use ($request) {
                    $query->where('type', $request->type);
                })->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allRadiologyTest(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = RadiologyService::where('tenant_id', $tenantId)->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allMedicine(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = PharmacyRequest::where('tenant_id', $tenantId)->with('pharmacy')->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allService(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = Service::where('tenant_id', $tenantId)->orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allServiceUnits(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = ServiceUnit::where('tenant_id', $tenantId)->orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
