<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Laboratory extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_lab';

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visitno', 'visitno');
    }

    public function billingLogs()
    {
        return $this->hasOneThrough(
            BillingLog::class,         // Final model
            PatientVisit::class,       // Intermediate model
            'visitno',                 // Foreign key on PatientVisit for PatientVisitLab (referenced by 'visitno')
            'visit_id',                // Foreign key on BillingLog pointing to PatientVisit (visit_id = id)
            'visitno',                 // Local key on PatientVisitLab
            'id'                       // Local key on PatientVisit
        );
    }
}
