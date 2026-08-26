<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bed extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'available_bed_number' => 'integer',
        'occupied' => 'boolean',
        'status' => 'boolean',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
