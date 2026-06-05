<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CareNote extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    public function writer()
    {
        return $this->belongsTo(User::class, 'written_by');
    }

    public function updated_by () {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
