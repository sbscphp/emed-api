<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Patient;

class PatientDocument extends Model
{
    protected $guarded = ['id'];
    protected $table = 'patient_documents';
    protected $connection = 'tenant';
    protected $casts = [
        'document_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
