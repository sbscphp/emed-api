<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingLogDetail extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    public function serviceUnit()
    {
        return $this->belongsTo(ServiceUnit::class, 'service_unit_id');
    }

    public function billingLog()
    {
        return $this->belongsTo(BillingLog::class, 'billing_id');
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class, 'treatment_id');
    }

    public function labInvestigation()
    {
        return $this->belongsTo(Laboratory::class, 'lab_test_id');
    }

    public function radiologyInvestigation()
    {
        return $this->belongsTo(Radiology::class, 'radiology_test_id');
    }
}
