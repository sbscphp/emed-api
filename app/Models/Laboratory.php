<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laboratory extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_lab';
    protected $fillable = [
        'patient_id',
        'admin_id',
        'consultation_id',
        'visitno',
        'lab_dept',
        'test_name',
        'ordered_test',
        'others',
        'status'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
}
