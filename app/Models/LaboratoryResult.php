<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryResult extends Model
{
    use HasFactory;
    protected $connection = 'tenant';
    protected $guarded = ["id"];
    protected $table = 'lab_test_results';

    public function patientVisitLab()
    {
        return $this->belongsTo(Laboratory::class, 'patient_visit_lab_id');
    }

    public function parameter()
    {
        return $this->belongsTo(LabParameter::class, 'lab_parameter_id');
    }
}
