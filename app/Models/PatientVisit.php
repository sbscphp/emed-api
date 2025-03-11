<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientVisit extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = ['patient_id', 'arrival_date', 'departure_date','stage', 'status', 'visitno', 'visit_date'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
