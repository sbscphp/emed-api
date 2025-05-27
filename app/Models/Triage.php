<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Triage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'patient_id',
        'user_id',
        'blood_pressure',
        'pulse_bpm',
        'sugar_level',
        'weight_kg',
        'temperature',
        'severity',
    ];
    protected $casts = [
        'blood_pressure' => 'array',
    ];


    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }
}
