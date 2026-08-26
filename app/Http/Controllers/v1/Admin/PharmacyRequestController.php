<?php
// app/Http/Controllers/v1/Admin/PharmacyRequestController.php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\GeneralEnums;
use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePharmacyRequest;
use App\Models\PharmacyRequest;
use App\Responser\JsonResponser;
use App\Services\PharmacyRequest\PharmacyRequestService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PharmacyRequestController extends Controller
{
    protected $service;

    public function __construct(PharmacyRequestService $service)
    {
        $this->service = $service;
    }

    public function store(CreatePharmacyRequest $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        config(['database.default' => 'tenant']);
        // Merge validated data with tenant_id
        $validated = array_merge($request->validated(), [
            'tenant_id' => $tenantId,
        ]);

        $data = $this->service->create($validated);
        return JsonResponser::send(false, 'Pharmacy request submitted successfully.', $data);
    }

    public function index(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $search = $request->input('search');
            $from = $request->from;
            $to = $request->to;
            $tenantId = $request->header('X-Tenant-ID');
            $paginate = $request->paginate;
            $data = $this->service->all($search, $from, $to, $paginate, $tenantId);

            if ($request->has('export')) {
                $exportData = $data->map(function ($item) {
                    return [
                        'Pharmacy Name'       => $item->pharmacy->name ?? '',
                        'Requested By'        => $item->requested_by ?? '',
                        'Requested Date'      => $item->requested_date ?? '',
                        'Urgency Level'       => ucfirst($item?->urgency_level ?? ""),
                        'Product/Drug'        => $item->product ?? "",
                        'Category'            => $item->category ?? "",
                        'Quantity Requested'  => $item->quantity_requested ?? "",
                        'Reason'              => $item->reason_for_request ?? "",
                    ];
                });

                if ($request->export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'pharmacy-requests.pdf');
                }

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'pharmacy-requests.xlsx');
                }

                return JsonResponser::send(true, 'Invalid export format.', [], 400);
            }

            return JsonResponser::send(false, 'Pharmacy requests retrieved.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to retrieve pharmacy requests.', [], 500, $e);
        }
    }


    public function show($id)
    {
        config(['database.default' => 'tenant']);
        $data = $this->service->find($id);
        return JsonResponser::send(false, 'Pharmacy request retrieved.', $data);
    }

    public function supplyRequest(Request $request, $id)
    {
        config(['database.default' => 'tenant']);
        $tenantId = $request->header('X-Tenant-ID');
        $drug = PharmacyRequest::with('inventory')->find($id);
        if (!$drug) {
            return JsonResponser::send(true, 'Drug not found.', [], 422);
        }

        if (!$drug->inventory) {
            return JsonResponser::send(true, 'Inventory record not found for this drug.', [], 422);
        }

        if (!empty($drug->inventory->expiry_date) && Carbon::parse($drug->inventory->expiry_date)->isPast()) {
            return JsonResponser::send(true, 'Drug expired', [], 422);
        }

        if ($request->quantity_supplied > $drug->inventory->quantity) {
            return JsonResponser::send(true, 'Quantity supplied is greater than quantity available in inventory stock', [], 422);
        }

        if ($request->quantity_supplied > $drug->quantity_requested) {
            return JsonResponser::send(true, 'Quantity supplied is greater than quantity requested', [], 422);
        }

        $data = $this->service->supply($request->all(), $id);
        return JsonResponser::send(false, 'Pharmacy request supplied successfully.', $data);
    }

    public function updateRequest(Request $request, $id)
    {
        config(['database.default' => 'tenant']);
        $data = $this->service->update($request->all(), $id);
        return JsonResponser::send(false, 'Pharmacy request updated successfully.', $data);
    }

    public function deleteRequest($id)
    {
        config(['database.default' => 'tenant']);
        $this->service->delete($id);
        return JsonResponser::send(false, 'Pharmacy request deleted successfully.');
    }
}
