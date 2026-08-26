<?php

namespace App\Repositories\MedicationInventory;

use App\Enums\GeneralEnums;
use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\MedicationInventory;
use App\Http\Resources\MedicationInventoryResource;
use Carbon\Carbon;

class MedicationInventoryRepository implements MedicationInventoryRepositoryInterface
{
    public function create(array $data)
    {
        $supportDoc = $data['support_doc'];
        $supportFile = $data['support_file'];
        if (!empty($data['support_doc'])) {
            $supportDoc = FileUploadHelper::singleStringFileUpload($data['support_doc'], 'shipment');
        }
        if (!empty($data['support_file'])) {
            $supportFile = FileUploadHelper::singleStringFileUpload($data['support_file'], 'shipment');
        }
        $data['support_doc'] = $supportDoc;
        $data['support_file'] = $supportFile;

        $shipment_no = GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => MedicationInventory::class,
            'modelField' => 'shipment_no',
            'prefix' => 'SHIP',
            'idLength' => 6,
        ]);
        $data['shipment_no'] = $shipment_no;

        return MedicationInventory::create($data);
    }

    public function getAllWithFilters(array $filters = [], ?string $export = null, $tenantId = null)
    {
        $query = MedicationInventory::where('tenant_id', $tenantId)->with(['medication', 'pharmacy', 'vendor'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['shipment_status'])) {
            $query->where('shipment_status',  $filters['shipment_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_no', 'like', "%{$search}%")
                    ->orWhere('shipment_status', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->orWhere('shipment_no', 'like', "%{$search}%")
                    // ->orWhereHas('pharmacy', function ($pharmacyQuery) use ($search) {
                    //     $pharmacyQuery->where('name', 'like', "%{$search}%");
                    // })
                    // ->orWhereHas('medication', function ($medicationQuery) use ($search) {
                    //     $medicationQuery->where('medicine_name', 'like', "%{$search}%")
                    //         ->orWhere('generic_name', 'like', "%{$search}%")
                    //         ->orWhere('brand_name', 'like', "%{$search}%")
                    //         ->orWhere('medicine_type', 'like', "%{$search}%");
                    // })
                    ->orWhereHas('vendor', function ($qu2) use ($search) {
                        $qu2->where('vendor_name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['medicine_type'])) {
            $query->whereHas('medication', function ($medicationQuery) use ($filters) {
                $medicationQuery->where('medicine_type', $filters['medicine_type']);
            });
        }

        if (!empty($filters['vendor_name'])) {
            $query->whereHas('vendor', function ($vendorQuery) use ($filters) {
                $vendorQuery->where('vendor_name', $filters['vendor_name']);
            });
        }

        if (!empty($filters['medicine_name'])) {
            $query->whereHas('medication', function ($medicationQuery) use ($filters) {
                $medicationQuery->where('medicine_name', 'like', "%{$filters['medicine_name']}%");
            });
        }

        if (!empty($filters['brand_name'])) {
            $query->where('brand_name', 'like', "%{$filters['brand_name']}%");
        }

        if (!empty($filters['generic_name'])) {
            $query->whereHas('medication', function ($medicationQuery) use ($filters) {
                $medicationQuery->where('generic_name', 'like', "%{$filters['generic_name']}%");
            });
        }

        if (!empty($filters['to']) && !empty($filters['from'])) {
            $from = Carbon::parse($filters['from'])->startOfDay();
            $to = Carbon::parse($filters['to'])->endOfDay();
            $query->whereBetween('created_at', [$from, $to]);
        }

        // if ($export) {
        //     $items = $query->latest()->get()->map($transformItem);

        //     if ($export === 'csv') {
        //         return ExportHelper::streamCsv($items->toArray(), null, 'medication_inventory.csv');
        //     }

        //     if ($export === 'pdf') {
        //         return ExportHelper::downloadPdf($items->toArray(), 'medication_inventory.pdf');
        //     }

        //     throw new \InvalidArgumentException('Invalid export format specified');
        // }

        // $paginated = $query->latest()->paginate(10);

        $page = request()->get('page', 1);
        $paginated = $query->paginate(10, ['*'], 'page', $page);

        $medical = MedicationInventoryResource::collection($paginated->getCollection())->toArray(request());

        if ($export) {
            // $items = $query->latest()->get()->map($transformItem);
            $items = $query->get()->map(function ($item) {
                return [
                    'Shipment No' => $item->shipment_no,
                    'Drug/Product' => $item->brand_name,
                    'Quantity' => $item->received_qty,
                    'Price' => '₦' . number_format($item->price, 2),
                    'Vendor' => optional($item->vendor)->vendor_name,
                    'Date' => optional($item->created_at)->toDateString(),
                    'Shipment Status' => ucfirst($item->shipment_status),
                ];
            });

            if ($export === 'csv') {
                return ExportHelper::streamCsv($items->toArray(), null, 'medication_inventory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($items->toArray(), 'medication_inventory.pdf');
            }

            throw new \InvalidArgumentException('Invalid export format specified');
        }


        $fetch = [
            'data' => $medical,
            //'test' => $query->get(),
            'pages' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'link' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ]
        ];

        return   collect($fetch);

        //   return $paginated->getCollection();
    }


    public function find($id)
    {
        return MedicationInventory::with(['medication', 'pharmacy', 'vendor'])->find($id);
    }
}
