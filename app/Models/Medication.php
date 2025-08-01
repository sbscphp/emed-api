<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    use HasFactory;
    protected $connection = 'tenant';
    protected $guarded = ['id'];
    protected $fillable = [
        'id',
        'generic_name',
        'brand_name',
        'medicine_name',
        'medicine_type',
        'cost_price',
        'selling_price',
        'reg_no',
        'manufacturer',
        'medicine_status',
        'pharmacy_id',
        'active_ingredent'
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
