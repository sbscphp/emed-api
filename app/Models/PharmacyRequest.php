<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacyRequest extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'pharmacy_id',
        'requested_by',
        'requested_date',
        'urgency_level',
        'product',
        'category',
        'quantity_requested',
        'reason_for_request',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
