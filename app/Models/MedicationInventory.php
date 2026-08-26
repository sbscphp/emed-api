<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationInventory extends Model
{
    protected $table = 'medication_inventory';
    protected $connection = 'tenant';
    //protected $connection = 'landlord';
    protected $guarded = ['id'];

    protected $casts = [
        'mfg_date' => 'date',
        'expiry_date' => 'date',
        'date_of_shipment' => 'date',
        'expected_delivery_date' => 'date',
    ];

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }
}
