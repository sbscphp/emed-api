<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsultantRateCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $casts = [
        'first_visit_price' => 'decimal:2',
        'returning_price'   => 'decimal:2',
        'markup_value'      => 'decimal:2',
        'is_default'        => 'boolean',
    ];
}
