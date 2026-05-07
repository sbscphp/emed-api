<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bed extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'ward_id',
        'bed_number',
        'number_of_available',
        'occupied',
        'status',
    ];

    protected $casts = [
        'number_of_available' => 'integer',
        'occupied' => 'boolean',
        'status' => 'boolean',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
