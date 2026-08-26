<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ward extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
        'cost' => 'decimal:2',
    ];

    public function bed()
    {
        return $this->hasOne(Bed::class);
    }
}
