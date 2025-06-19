<?php

namespace App\Repositories\MedicationInventory;

use App\Helpers\ExportHelper;
use App\Models\MedicationInventory;
use App\Http\Resources\MedicationInventoryResource;
class MedicationInventoryRepository implements MedicationInventoryRepositoryInterface
{
    public function create(array $data)
    {
        $data['current_stock'] = $data['received_qty'];
        return MedicationInventory::create($data);
    }

    public function getAllWithFilters(array $filters = [], ?string $export = null)
    {
        $query = MedicationInventory::with(['medication', 'pharmacy']);

        if (!empty($filters['shipment_status'])) {
            $query->where('shipment_status', $filters['shipment_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_no', 'like', "%$search%")
                    ->orWhere('shipment_status', 'like', "%$search%")
                    ->orWhereHas('medication', function ($mq) use ($search) {
                        $mq->where('medicine_name', 'like', "%$search%")
                            ->orWhere('brand_name', 'like', "%$search%")
                            ->orWhere('generic_name', 'like', "%$search%");
                    })
                    ->orWhereHas('pharmacy', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%$search%");
                    });
            });
        }
        // $transformItem = function ($item) {
        //     $sellingPrice = optional($item->medication)->selling_price ?? 0;
        //     $totalPrice = $item->received_qty * $sellingPrice;

        //     return [
        //         'id' => $item->id,
        //         'BatchNo' => $item->batch_no,
        //         'ShipmentStatus' => $item->shipment_status,
        //         'MedicineName' => $item->medication->medicine_name ?? '',
        //         'BrandName' => $item->medication->brand_name ?? '',
        //         'GenericName' => $item->medication->generic_name ?? '',
        //         'Pharmacy' => $item->pharmacy->name ?? '',
        //         'ReceivedQty' => $item->received_qty,
        //         'SellingPrice' => $sellingPrice,
        //         'TotalPrice' => $totalPrice,
        //         'CreatedAt' => $item->created_at->toDateTimeString(),
        //     ];
        // };

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
        $paginated = $query->latest()->paginate(10, ['*'], 'page', $page);
        
        // $paginated->getCollection()->transform($transformItem);
    //     $medical = MedicationInventoryResource::collection($paginated)->toArray(request());

    //  $data =   [
    // 'data' => $medical,
    // 'meta' => [
    //     'current_page' => $paginated->currentPage(),
    //     'last_page' => $paginated->lastPage(),
    //     'per_page' => $paginated->perPage(),
    //     'total' => $paginated->total(),
    // ]];
    //   return $data; 
    
    $medical = MedicationInventoryResource::collection($paginated)->toArray(request());

        $data = [
            'data' => $medical,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'link' => $paginated->links()
            ]
        ];

        return response()->json($data);
    }


    public function find($id)
    {
        return MedicationInventory::with(['medication', 'pharmacy'])->find($id);
    }
}
