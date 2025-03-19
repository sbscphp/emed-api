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
    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'assigned_pharmacist');
    }
}
