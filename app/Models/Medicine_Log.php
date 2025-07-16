<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine_Log extends Model
{
    protected $table = 'medicine__logs';
    protected $connection = 'tenant';
    protected $fillable = [
        'patient_id',
        'medication_id',
        'pharmacy_id',
        'presscribed_drug',
        'patient_status',
        'status',
        'action',
        'arrival_date'
    ];
}
