<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabService extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    public function labParameters()
    {
        return $this->hasMany(LabParameter::class, 'lab_test_id')
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class);
    }
}
