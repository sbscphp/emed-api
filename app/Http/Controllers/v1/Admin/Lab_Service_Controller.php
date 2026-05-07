<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab_Edit_Service_Request;
use App\Http\Requests\Lab_Service_Request;
use App\Models\LabService;
use App\Models\ServiceUnit;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;
use App\Helpers\ExportHelper;

class Lab_Service_Controller extends Controller
{

    public function create_lab_service(Lab_Service_Request $request)
    {
        try {
            $validated = $request->validated();
            $tenantId = $request->header('X-Tenant-ID');
            $serviceunit = ServiceUnit::where("name", "Laboratory")->where('tenant_id', $tenantId)->first() ?? null;
            $data = LabService::create([
                'tenant_id'        => $tenantId,
                "service_unit_id" => $serviceunit->id,
                "name" => $validated['name'],
                "price" => $validated['price'],
                "class" => $validated['class'] ?? null,
                "type" => $validated['type'],
                "service_category_id" => $validated['service_category_id'],
            ]);
            $data->load('serviceCategory');
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }



    public function edit_lab_service(Lab_Edit_Service_Request $request)
    {
        try {
            $validated = $request->validated();
            $service = LabService::find($validated['id']);
            if ($service) {
                $service->update([
                    'name' => $validated['name'],
                    'price' => $validated['price'],
                    'class' => $validated['class'] ?? null,
                    'type' => $validated['type'],
                    'service_category_id' => $validated['service_category_id'],
                ]);
                $service->load('serviceCategory');
                return JsonResponser::send(false, 'edit successfully.', $service);
            }
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }

    public function delete_lab_service($id)
    {
        try {
            $service = LabService::find($id);
            if ($service) {
                $service->delete();
                return JsonResponser::send(false, 'delete successfully.', []);
            }
            return JsonResponser::send(true, 'Service not found.', [], 404);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }

    public function labService_all(Request $request)
    {
        try {
            $validated = $request->validate([
                "search" => "nullable|string",
                "export" => "nullable|string|in:pdf,csv"
            ]);

            if (!empty($validated['export'])) {
                $exportData = LabService::all()->toArray();

                if ($validated['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'service.csv');
                }

                if ($validated['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'service.pdf');
                }
            }
            $tenantId = $request->header('X-Tenant-ID');

            $services = LabService::where('tenant_id', $tenantId)
                ->with('serviceCategory')
                ->when(!empty($validated['search']), function ($query) use ($validated) {
                    $search = $validated['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('class', 'LIKE', "%{$search}%")
                            ->orWhere('price', 'LIKE', "%{$search}%");
                    });
                })->paginate(10);


            return JsonResponser::send(false, 'featch successfully.', [
                "data" => $services,
                "total" => LabService::sum('price')
            ]);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }
}
