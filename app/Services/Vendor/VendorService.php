<?php

namespace App\Services\Vendor;

use App\Models\MedicationInventory;
use App\Models\Vendor;
use App\Repositories\Vendor\VendorInterface;
use Illuminate\Support\Facades\DB;

/**
 * Class VendorService
 * 
 * This class provides services related to Vendor operations and acts as a 
 * layer between the Controller and the VendorRepository.
 */
class VendorService
{
    protected VendorInterface $VendorInterface;
    /**
     * Vendor constructor.
     * 
     * @param VendorInterface $VendorInterface
     */
    public function __construct(VendorInterface $VendorInterface)
    {
        $this->VendorInterface = $VendorInterface;
    }

    /**
     * Retrieve all Vendor.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all(array $filters = [], ?string $export = null, $from, $to)
    {
        return $this->VendorInterface->all($filters, $export, $from, $to);
    }

    /**
     * Create a new Vendor using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Vendor
     */
    public function create(array $data)
    {
        return $this->VendorInterface->create($data);
    }


    /**
     * Update an existing Vendor with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Vendor
     */
    public function update(array $data, $id)
    {
        return $this->VendorInterface->update($data, $id);
    }


    /**
     * Delete a Vendor by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->VendorInterface->delete($id);
    }


    /**
     * Find a Vendor by their ID.
     * 
     * @param int $id
     * @return \App\Models\Vendor
     */
    public function find($id)
    {
        return $this->VendorInterface->find($id);
    }


    /**
     * Find an existing Vendor  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Vendor
     */
    public function findByAttribute($attr, $value)
    {
        return $this->VendorInterface->findByAttribute($attr, $value);
    }

    public function getVendorStats()
    {
<<<<<<< HEAD

        // dd(json_encode([MedicationInventory::all(), "\r\n", Vendor::all()]));
        $totalVendors = Vendor::count();
=======
        // $totalVendors = Vendor::count();
>>>>>>> 37e1d33 (update)

        // $totalSpend = MedicationInventory::sum(DB::raw('received_qty * price'));

        // $pendingSupplyOrders = MedicationInventory::whereHas('vendor', function ($q) {
        //     $q->where('status', 'active');
        // })->where('shipment_status', 'pending')->count();

<<<<<<< HEAD
        $mostSuppliedItem = MedicationInventory::select('brand_name', DB::raw('SUM(CAST(received_qty AS UNSIGNED)) as total'))
            ->groupBy('brand_name')
            ->orderByDesc('total')
            ->first();
=======
        // $mostSuppliedItem = MedicationInventory::select('brand_name', DB::raw('SUM(received_qty) as total'))
        //     ->groupBy('brand_name')
        //     ->orderByDesc('total')
        //     ->first();
>>>>>>> 37e1d33 (update)

        // return [
        //     'total_vendors' => $totalVendors,
        //     'total_spend' => $totalSpend,
        //     'pending_supply_orders' => $pendingSupplyOrders,
        //     'most_supplied_item' => $mostSuppliedItem ? $mostSuppliedItem->brand_name : null,
        //     'most_supplied_qty' => $mostSuppliedItem ? (int) $mostSuppliedItem->total : 0,
        // ];


<<<<<<< HEAD
        // $totalVendors = Vendor::count();

        // $totalSpend = MedicationInventory::sum(DB::raw('received_qty * price'));

        // $pendingSupplyOrders = MedicationInventory::whereHas('vendor', function ($q) {
        //     $q->where('status', 'active');
        // })->where('shipment_status', 'pending')->count();

        // $mostSuppliedItem = MedicationInventory::select('brand_name', DB::raw('SUM(received_qty) as total'))
        //     ->groupBy('brand_name')
        //     ->orderByDesc('total')
        //     ->first();

        // return [
        //     'total_vendors' => $totalVendors,
        //     'total_spend' => (float) $totalSpend,
        //     'pending_supply_orders' => $pendingSupplyOrders,
        //     'most_supplied_item' => optional($mostSuppliedItem)->brand_name,
        //     'most_supplied_qty' => optional($mostSuppliedItem)->total ? (int) $mostSuppliedItem->total : 0,
        // ];
    }
=======
              $totalVendors = Vendor::count();
>>>>>>> 37e1d33 (update)

            $totalSpend = MedicationInventory::sum(DB::raw('received_qty * price'));

            $pendingSupplyOrders = MedicationInventory::whereHas('vendor', function ($q) {
                $q->where('status', 'active');
            })->where('shipment_status', 'pending')->count();

            $mostSuppliedItem = MedicationInventory::select('brand_name', DB::raw('SUM(received_qty) as total'))
                ->groupBy('brand_name')
                ->orderByDesc('total')
                ->first();

            return [
                'total_vendors' => $totalVendors,
                'total_spend' => (float) $totalSpend,
                'pending_supply_orders' => $pendingSupplyOrders,
                'most_supplied_item' => optional($mostSuppliedItem)->brand_name,
                'most_supplied_qty' => optional($mostSuppliedItem)->total ? (int) $mostSuppliedItem->total : 0,
            ];


    public function update_status($validated, $id)
    {

        return $this->VendorInterface->update_status($validated, $id);
    }
}
