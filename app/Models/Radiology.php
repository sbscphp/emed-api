<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Radiology extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_radiology';

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    public function billingLogDetail()
    {
        return $this->belongsTo(BillingLogDetail::class, 'id', 'radiology_test_id');
    }

    public function result()
    {
        return $this->belongsTo(RadiologyResult::class, 'id', 'radiology_id');
    }
}
