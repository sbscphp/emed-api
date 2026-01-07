<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ExportHelper;
use App\Http\Requests\Radiology__Editservice_Request;
use App\Http\Requests\Radiology_service_Request;
use App\Models\RadiologyService;
use Illuminate\Http\Request;
use App\Models\ServiceUnit;
use App\Responser\JsonResponser;
use Barryvdh\DomPDF\Facade\Pdf;

class Radiology_service_Controller extends Controller
{


    public function create_radiology_service(Radiology_service_Request $request)
    {
        try {
            $validated = $request->validated();
            $tenantId = $request->header('X-Tenant-ID');
            $serviceunit = ServiceUnit::where("name", "Radiology")->where('tenant_id', $tenantId)->first() ?? null;
            $data = RadiologyService::create([
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



    public function edit_radiology_service(Radiology__Editservice_Request $request)
    {
        try {
            $validated = $request->validated();
            $service = RadiologyService::find($validated['id']);
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

            $tenantId = $request->header('X-Tenant-ID');

            // Base query
            $query = RadiologyService::where('tenant_id', $tenantId)
                ->when(!empty($validated['search']), function ($query) use ($validated) {
                    $search = $validated['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('price', 'LIKE', "%{$search}%");
                    });
                });

            // EXPORT
            if (!empty($validated['export'])) {
                $records = $query->orderBy('id', 'DESC')->get();
                return $this->exportRadiologyServices($records, $validated['export']);
            }

            // NORMAL PAGINATED RESPONSE
            $services = $query->orderBy('id', 'DESC')->paginate(10);

            return JsonResponser::send(false, 'Fetched successfully.', $services);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error.', [], 500, $th);
        }
    }

    public function exportRadiologyServices($records, $format)
    {
        $exportData = $records->map(function ($service) {
            return [
                'Service Name'   => $service->name,
                'Price'          => number_format($service->price, 2),
                // 'Category'       => $service->category ?? 'N/A',
                // 'Status'         => $service->status ?? 'Active',
                // 'Created Date'   => optional($service->created_at)->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // CSV Export
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'radiology_services.csv');
        }

        // PDF Export
        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView(
                'exports.patients',
                ['patients' => $exportData]
            )->setPaper('A4', 'landscape');

            return $pdf->download('radiology_services.pdf');
        }

        throw new \Exception("Invalid export format.");
    }
}
