<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Tenant ;

class PatientInformation extends Model
{

    protected $guarded = ['id'];
    protected $fillable = [
        'firstname',
        'lastname',
        'dob',
        'age',
        'gender',
        'bloodgroup',
        'bloodgenotype',
        'email',
        'patient_type',
        'marital_status',
        'phoneno',
        'occupation',
        'homeaddress',
        'companyaddress',
        'religion',
        'stateoforigin',
        'lga',
        'tribe',
        'cardno',
        'receiptno',
    ];
}
