<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_treatment';
    protected $fillable = [
        'patient_id',
        'admin_id',
        'consultation_id',
        'visitno',
        'lab_dept',
        'test_name',
        'medication',
        'dosage',
        'weight',
        'period',
        'duration',
        'route',
        'remark',
        'receiptno',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
