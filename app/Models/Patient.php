<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'firstname',
        'lastname',
        'dob',
        'age',
        'gender',
        'bloodgroup',
        'genotype',
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
        'status',
        'service_id',
        'arrival_time',
        'depature_time'
    ];

    public function service()
    {
        return $this->belongsTo(ServiceDepartment::class);
    }

    public function nextOfKin()
    {
        return $this->hasOne(NextOfKin::class);
    }

    public function emergencyContact()
    {
        return $this->hasOne(EmergencyContact::class);
    }

}
