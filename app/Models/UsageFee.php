<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsageFee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $connection = 'landlord';
    protected $table = 'usage_fees';

    protected $casts = [
        'is_general_visit' => 'boolean',
        'is_unique_visit' => 'boolean',
        'amount' => 'decimal:2',
        'cycles' => 'array',
    ];
}
