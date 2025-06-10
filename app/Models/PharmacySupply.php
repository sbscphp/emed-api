<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacySupply extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'pharmacy_id',
        'product_name',
        'product_category',
        'quantity_supplied',
        'stock_level',
        'supplier_name',
        'batch_number',
        'supplied_date',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
