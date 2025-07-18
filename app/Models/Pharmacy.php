<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'type',
        'address',
        'state_id',
        'phone_number',
        'assigned_pharmacist',
        'license_number',
        'active',
        'email_address',
        'pharmacy_id',
        'opening_time',
        'closing_time'
    ];


    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
    /**
     * @property User $pharmacist
     * @property \Illuminate\Database\Eloquent\Collection|Treatment[] $treatments
     */
    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'assigned_pharmacist');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }

    public function treatments_one()
    {
        return $this->hasOne(Treatment::class);
    }

    public function consultations()
    {
        return $this->hasManyThrough(
            Consultation::class,
            Treatment::class,
            'pharmacy_id',
            'id',
            'id',
            'consultation_id'
        );
    }

    public function patients()
    {
        return $this->hasManyThrough(
            Patient::class,
            Treatment::class,
            'pharmacy_id',
            'id',
            'id',
            'patient_id'
        )
            ->selectRaw('patients.id as patient_id, patients.firstname, patients.lastname, patients.patientno, patients.status');
    }
}
