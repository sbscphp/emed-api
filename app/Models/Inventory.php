<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $connection = 'tenant';
    protected $guarded = ['id'];

    public function calculateStatus()
    {
        // if (Carbon::parse($this->expiry_date)->isPast()) {
        //     return 'Expired';
        // }

        return $this->quantity < $this->reorder_level ? 'Low Stock' : 'Sufficient';
    }

    public function medicineType()
    {
        return $this->belongsTo(MedicineType::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class, 'medication_id', 'id');
    }

    protected static function booted()
    {
        static::saving(function ($inventory) {
            $inventory->status = $inventory->calculateStatus();
        });
    }
}
