<?php



namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ExportHelper;
use App\Http\Requests\Consultation_service_Edit_request;
use App\Http\Requests\Consultation_service_request;
use App\Models\Consultation_service as ModelsConsultation_service;
use App\Models\Service;
use App\Models\ServiceUnit;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;

class Consultation_Service_Bill extends Controller
{
    public function create_consultation_service(Consultation_service_request $request)
    {
        try {
            $validated = $request->validated();
            $serviceunit = ServiceUnit::where("name", "Consultation")->first() ?? null;
            $tenantId = $request->header('X-Tenant-ID');
            // $data = ModelsConsultation_service::create([
            //     "service_unit_id" => $serviceunit->id,
            //     "name" => $validated['name'],
            //     "price" => $validated['price']
            // ]);
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

    public function edit_consultation_service(Consultation_service_Edit_request $request)
    {
        try {
            $validated = $request->validated();
            // $service = ModelsConsultation_service::find($validated['id']);
            // if ($service) {
            //     $service->update($validated);
            //     return JsonResponser::send(false, 'edit successfully.', $service);
            // }
            $service = Service::find($validated['id']);
            if ($service) {
                $service->update($validated);
                return JsonResponser::send(false, 'edit successfully.', $service);
            }
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }

    public function all_consultation_service(Request $request)
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

    // public function all_consultation_service(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             "search" => "nullable|string",
    //             "export" => "nullable|string|in:pdf,csv"
    //         ]);

    //         if (!empty($validated['export'])) {
    //             $exportData = ModelsConsultation_service::all()->toArray();

    //             if ($validated['export'] === 'csv') {
    //                 return ExportHelper::streamCsv($exportData, null, 'service.csv');
    //             }

    //             if ($validated['export'] === 'pdf') {
    //                 return ExportHelper::downloadPdf($exportData, 'service.pdf');
    //             }
    //         }

    //         $services = ModelsConsultation_service::when(!empty($validated['search']), function ($query) use ($validated) {
    //             $search = $validated['search'];

    //             $query->where(function ($q) use ($search) {
    //                 $q->where('name', 'LIKE', "%{$search}%")
    //                     ->orWhere('price', 'LIKE', "%{$search}%");
    //             });
    //         })->paginate(10);
    //         return JsonResponser::send(false, 'featch successfully.', $services);
    //     } catch (\Throwable $th) {
    //         return JsonResponser::send(true, 'Error  .', [], 500, $th);
    //     }
    // }
}
