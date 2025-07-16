<?php

namespace App\Repositories\MedicationInventory;

use App\Helpers\ExportHelper;
use App\Models\MedicationInventory;
use App\Http\Resources\MedicationInventoryResource;
use Carbon\Carbon;

class MedicationInventoryRepository implements MedicationInventoryRepositoryInterface
{
    public function create(array $data)
    {
        $data['current_stock'] = $data['received_qty'];
        return MedicationInventory::create($data);
    }

    public function getAllWithFilters(array $filters = [], ?string $export = null)
    {
        $query = MedicationInventory::with(['medication', 'pharmacy'])->orderBy('created_at', 'desc');

        if (!empty($filters['shipment_status'])) {
            $query->where('shipment_status', 'like', "%{$filters['shipment_status']}%");
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_no', 'like', "%{$search}%")
                    ->orWhere('shipment_status', 'like', "%{$search}%")
                    ->orWhereHas('pharmacy', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['medicine_name'])) {
            $product =   $filters['medicine_name'];
            $query->whereHas('medication', function ($mq) use ($product) {
                $mq->where('medicine_name', 'like', "%{$product}%");
            });
        }



        if (!empty($filters['brand_name'])) {
            $product =   $filters['brand_name'];
            $query->whereHas('medication', function ($mq) use ($product) {
                $mq->where('brand_name', 'like', "%{$product}%");
            });
        }


        if (!empty($filters['generic_name'])) {
            $product =   $filters['generic_name'];
            $query->whereHas('medication', function ($mq) use ($product) {
                $mq->where('generic_name', 'like', "%{$product}%");
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

            if ($export === 'csv') {
                return ExportHelper::streamCsv($medical, null, 'medication_inventory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($medical, 'medication_inventory.pdf');
            }

            throw new \InvalidArgumentException('Invalid export format specified');
        }


        $fetch = [
            'data' => $medical,
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
        return MedicationInventory::with(['medication', 'pharmacy'])->find($id);
    }
}
