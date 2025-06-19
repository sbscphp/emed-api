<?php

namespace App\Repositories\MedicationInventory;

use App\Helpers\ExportHelper;
use App\Models\MedicationInventory;

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

            //      'vendor_id' => 'nullable|exists:tenant.vendors,id',
            // 'medication_id' => 'nullable|exists:tenant.medications,id',
            // 'pharmacy_id' => 'nullable|exists:tenant.pharmacies,id',
            // 'shipment_no' => 'nullable|string|max:255',
            // 'batch_no' => 'required|string|max:255',
            // 'mfg_date' => 'required|date',
            // 'expiry_date' => 'required|date|after_or_equal:mfg_date',
            // 'date_of_shipment' => 'nullable|date|after_or_equal:today',
            // 'expected_delivery_date' => 'nullable|date|after_or_equal:date_of_shipment',
            // 'received_qty' => 'nullable|integer|min:1',
            // 'shipment_status' => 'required|in:pending,incomplete,complete,received',
            // 'active_ingredient' => 'nullable|string|max:255',
            // 'brand_name' => 'nullable|string|max:255',
            // 'price' => 'nullable|numeric|min:0',
        }

        //  $query->when(!empty($filters['search']), function ($q) use ($filters) {
        //         $search = $filters['search'];
        //         $q->where(function ($q) use ($search) {
        //             $q->where('batch_no',  $search)
        //             ->orWhere('shipment_status', $search)
        //             ->orWhereHas('medication', function ($mq) use ($search) {
        //                 $mq->where('medicine_name', $search)
        //                     ->orWhere('brand_name', $search)
        //                     ->orWhere('generic_name', $search);
        //             })
        //             ->orWhereHas('pharmacy', function ($pq) use ($search) {
        //                 $pq->where('name', $search);
        //             });
        //         });
        //     });


        $transformItem = function ($item) {
            $sellingPrice = optional($item->medication)->selling_price ?? 0;
            $totalPrice = $item->received_qty * $sellingPrice;

            return [
                'id' => $item->id,
                'BatchNo' => $item->batch_no,
                'ShipmentStatus' => $item->shipment_status,
                'MedicineName' => $item->medication->medicine_name ?? '',
                'BrandName' => $item->medication->brand_name ?? '',
                'GenericName' => $item->medication->generic_name ?? '',
                'Pharmacy' => $item->pharmacy->name ?? '',
                'ReceivedQty' => $item->received_qty,
                'SellingPrice' => $sellingPrice,
                'TotalPrice' => $totalPrice,
                'CreatedAt' => $item->created_at->toDateTimeString(),
            ];
        };

        if ($export) {
            $items = $query->latest()->get()->map($transformItem);

            if ($export === 'csv') {
                return ExportHelper::streamCsv($items->toArray(), null, 'medication_inventory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($items->toArray(), 'medication_inventory.pdf');
            }

            throw new \InvalidArgumentException('Invalid export format specified');
        }

        // $paginated = $query->latest()->paginate(10);
        // $paginated->getCollection()->transform($transformItem);
        //    return $paginated;
        $paginated = $query->latest()->paginate(200);
        $paginated->getCollection()->transform(function ($item) use ($transformItem) {
                return $transformItem($item);
            });

            return response()->json($paginated);

      
    }


    public function find($id)
    {
        return MedicationInventory::with(['medication', 'pharmacy'])->find($id);
    }
}
