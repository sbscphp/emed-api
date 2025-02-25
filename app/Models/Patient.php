<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

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
