<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
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
