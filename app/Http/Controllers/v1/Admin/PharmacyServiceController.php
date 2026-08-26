<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditPharmacyServiceRequest;
use App\Http\Requests\PharmacyServiceRequest;
use App\Models\ServiceUnit;
use App\Models\PharmacyService;
use App\Services\PharmacyRequest\PharmacyRequestService;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;
use App\Helpers\ExportHelper;

class PharmacyServiceController extends Controller
{

    private  function generateUniquePharmacyCode()
    {
        do {
            $code = rand(10000, 99999);
        } while (PharmacyService::where('registration_number', $code)->exists());

        return $code;
    }
    public function createpharmacyservice(PharmacyServiceRequest $request)
    {

        try {
            $validated = $request->validated();
            $tenantId = $request->header('X-Tenant-ID');
            $serviceunit = ServiceUnit::where("name", "Pharmacy")->first() ?? null;

            $data =  PharmacyService::create([
                'tenant_id'        => $tenantId,
                "name" => $validated['name'],
                "active_ingredent" => $validated['active_ingredent'],
                "price" => $validated['price'],
                "registration_number" => $this->generateUniquePharmacyCode(),
                "service_unit_id" => $serviceunit->id
            ]);
            return JsonResponser::send(false, 'successfully.',  $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'error', [], 500, $th);
        }
    }


    public function  editpharmacyservice(EditPharmacyServiceRequest $request)
    {
        try {
            $validated =  $request->validated();

            $pharmacyService =  PharmacyService::find($validated['id']);
            if ($pharmacyService) {
                $pharmacyService->update($validated);
                return JsonResponser::send(false, 'successfully.',  $pharmacyService);
            }
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'error', [], 500, $th);
        }
    }


    public function pharmacyService_all(Request $request)
    {
        try {
            $validated = $request->validate([
                "search" => "nullable|string",
                "export" => "nullable|string|in:pdf,csv"
            ]);

            if (!empty($validated['export'])) {
                $exportData = PharmacyService::all()->toArray();

                if ($validated['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'service.csv');
                }

                if ($validated['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'service.pdf');
                }
            }
            $tenantId = $request->header('X-Tenant-ID');

            $services = PharmacyService::where('tenant_id', $tenantId)
                ->when(!empty($validated['search']), function ($query) use ($validated) {
                    $search = $validated['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('active_ingredent', 'LIKE', "%{$search}%")
                            ->orWhere('registration_number', 'LIKE', "%{$search}%");
                    });
                })->paginate(10);


            return JsonResponser::send(false, 'featch successfully.', [
                "data" => $services,
                "total" => PharmacyService::sum('price')
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }
}
