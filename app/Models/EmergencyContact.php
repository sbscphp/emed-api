<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'emergency_contact';
    protected $fillable = [
        'patient_id',
        'firstname',
        'lastname',
        'gender',
        'relationship',
        'phoneno',
        'homeaddress',
        'stateoforigin',
        'lga',

    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
