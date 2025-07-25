<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation_service_request;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;

class Consultation_Service extends Controller
{

    // public function create_service(Consultation_service_request $request)
    // {
    //     try {
    //         $validated = $request->validated();
    //         $data = ServiceDepartment::create($validated);
    //         return JsonResponser::send(false, ' created successfully.', $data);
    //     } catch (\Throwable $th) {
    //         return JsonResponser::send(true, 'Error  .', [], 500, $th);
    //     }
    // }

    // public function edit_service(EditservichospitalRequest $request)
    // {
    //     try {
    //         $validated = $request->validated();
    //         $service = ServiceDepartment::find($validated['id']);
    //         if ($service) {
    //             $service->update($validated);
    //             return JsonResponser::send(false, 'edit successfully.', $service);
    //         }
    //     } catch (\Throwable $th) {
    //         return JsonResponser::send(true, 'Error  .', [], 500, $th);
    //     }
    // }

    // public function service(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             "search" => "nullable|string",
    //             "export" => "nullable|string|in:pdf,csv"
    //         ]);

    //         if (!empty($validated['export'])) {
    //             $exportData = ServiceDepartment::all()->toArray();

    //             if ($validated['export'] === 'csv') {
    //                 return ExportHelper::streamCsv($exportData, null, 'service.csv');
    //             }

    //             if ($validated['export'] === 'pdf') {
    //                 return ExportHelper::downloadPdf($exportData, 'service.pdf');
    //             }
    //         }

    //         $services = ServiceDepartment::when(!empty($validated['search']), function ($query) use ($validated) {
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
