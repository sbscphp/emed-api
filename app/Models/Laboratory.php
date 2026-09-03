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
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    /**
     * The doctor who ordered the test — the "Ordered By" line on the patient
     * app's result screen.
     *
     * Users live on the landlord connection, so this stays a plain belongsTo
     * (resolved with a separate query) and carries no database level foreign key.
     */
    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'consultedBy', 'id');
    }

    /**
     * Whether the laboratory has released this result.
     */
    public function getIsReleasedAttribute(): bool
    {
        return $this->status === 'Ready';
    }

    public function billingLogDetail()
    {
        return $this->belongsTo(BillingLogDetail::class, 'id', 'lab_test_id');
    }

    public function results()
    {
        return $this->hasMany(LaboratoryResult::class, 'patient_visit_lab_id');
    }

    public function testService()
    {
        return $this->belongsTo(LabService::class, 'test_id');
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
