<?php
// app/Http/Controllers/v1/Admin/PharmacyRequestController.php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePharmacyRequest;
use App\Responser\JsonResponser;
use App\Services\PharmacyRequest\PharmacyRequestService;
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
        config(['database.default' => 'tenant']);
        $data = $this->service->create($request->validated());
        return JsonResponser::send(false, 'Pharmacy request submitted successfully.', $data);
    }

    public function index(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $search = $request->input('search');
            $from = $request->from;
            $to = $request->to;
            $paginate = $request->paginate;
            $data = $this->service->all($search, $from, $to, $paginate);

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
}
