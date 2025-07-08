<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePharmacySupplyRequest;
use App\Responser\JsonResponser;
use App\Services\PharmacySupplier\PharmacySupplyService;
use Illuminate\Http\Request;

class PharmacySupplyController extends Controller
{
    protected $supplyService;

    public function __construct(PharmacySupplyService $supplyService)
    {
        $this->supplyService = $supplyService;
    }

    public function store(StorePharmacySupplyRequest $request)
    {
        $supply = $this->supplyService->createSupply($request->validated());
        return JsonResponser::send(false, 'Pharmacy supply added successfully.', $supply);
    }

    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $isExport = $request->has('export');
            $from = $request->from;
            $to = $request->to;
            $supplies = $this->supplyService->listSupplies($search, $isExport, $from, $to);

            $isEmpty = false;

            if ($isExport && $supplies->isEmpty()) {
                $isEmpty = true;
            }

            if (!$isExport && $supplies->total() === 0) {
                $isEmpty = true;
            }

            if ($isEmpty) {
                return JsonResponser::send(true, 'No pharmacy supplies found.', [], 200);
            }


            if ($isExport) {
                $exportData = $supplies->map(function ($supply) {
                    return [
                        'Pharmacy'        => $supply->pharmacy->name ?? '',
                        'Product Name'    => $supply->product_name,
                        'Category'        => $supply->product_category,
                        'Quantity'        => $supply->quantity_supplied,
                        'Stock Level'     => $supply->stock_level,
                        'Supplier'        => $supply->supplier_name,
                        'Batch Number'    => $supply->batch_number,
                        'Supplied Date'   => $supply->supplied_date,
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'pharmacy_supplies.csv');
                }

                if ($request->export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'pharmacy_supplies.pdf');
                }

                return JsonResponser::send(true, 'Invalid export format.', [], 400);
            }

            return JsonResponser::send(false, 'Supplies retrieved successfully.', $supplies);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function show($id)
    {
        $supply = $this->supplyService->getSupplyById($id);

        if (!$supply) {
            return JsonResponser::send(true, 'Supply not found.', [], 200);
        }

        return JsonResponser::send(false, 'Supply retrieved successfully.', $supply);
    }
}
