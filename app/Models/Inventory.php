<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        'batch_no',
        'item_name',
        'medicine_type_id',
        'quantity',
        'reorder_level',
        'supplier',
        'expiry_date',
        'note',
        'status',
    ];

    public function calculateStatus()
    {
        if (Carbon::parse($this->expiry_date)->isPast()) {
            return 'Expired';
        }

        return $this->quantity < $this->reorder_level ? 'Low Stock' : 'Sufficient';
    }

    public function medicineType()
    {
        return $this->belongsTo(MedicineType::class);
    }


    protected static function booted()
    {
        static::saving(function ($inventory) {
            $inventory->status = $inventory->calculateStatus();
        });
    }
}
