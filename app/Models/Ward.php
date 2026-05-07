<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ward extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'gender',
        'status',
        'bed_cost',
    ];

    protected $casts = [
        'status' => 'boolean',
        'bed_cost' => 'decimal:2',
    ];

    public function beds()
    {
        return $this->hasMany(Bed::class)->orderBy('id');
    }
}
