<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ExportHelper;
use App\Http\Requests\Radiology__Editservice_Request;
use App\Http\Requests\Radiology_service_Request;
use App\Models\Radiology_Service;
use Illuminate\Http\Request;
use App\Models\ServiceUnit;
use App\Responser\JsonResponser;

class Radiology_service_Controller extends Controller
{


    public function create_radiology_service(Radiology_service_Request $request)
    {
        try {
            $validated = $request->validated();
            $serviceunit = ServiceUnit::where("name", "Radiology")->first() ?? null;
            $data = Radiology_Service::create([
                "service_unit_id" => $serviceunit->id,
                "name" => $validated['name'],
                "price" => $validated['price']
            ]);
            return JsonResponser::send(false, ' created successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }



    public function edit_radiology_service(Radiology__Editservice_Request $request)
    {
        try {
            $validated = $request->validated();
            $service = Radiology_Service::find($validated['id']);
            if ($service) {
                $service->update($validated);
                return JsonResponser::send(false, 'edit successfully.', $service);
            }
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error  .', [], 500, $th);
        }
    }


    public function all_radiology_service(Request $request)
    {
        try {
            $validated = $request->validate([
                "search" => "nullable|string",
                "export" => "nullable|string|in:pdf,csv"
            ]);

            if (!empty($validated['export'])) {
                $exportData = Radiology_Service::all()->toArray();

                if ($validated['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'service.csv');
                }

                if ($validated['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'service.pdf');
                }
            }

            $services = Radiology_Service::when(!empty($validated['search']), function ($query) use ($validated) {
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
