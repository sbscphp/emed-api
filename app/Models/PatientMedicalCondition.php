<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientMedicalCondition extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'diagnosed_at' => 'date',
    ];

    /**
     * Who put the entry on the list.
     *
     * @var array<int, string>
     */
    public const SOURCES = ['Patient', 'Hospital'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Limit the query to the conditions of one patient.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPatient(Builder $query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }
}
