<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientAllergy extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    /**
     * The kinds of allergy the app's type picker offers.
     *
     * @var array<int, string>
     */
    public const TYPES = ['Drug', 'Food', 'Environmental', 'Other'];

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
     * Limit the query to the allergies of one patient.
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
