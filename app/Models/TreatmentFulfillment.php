<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentFulfillment extends Model
{
    protected $guarded = [];
    protected $connection = 'tenant';

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }
}
