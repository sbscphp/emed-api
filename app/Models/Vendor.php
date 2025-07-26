<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'id',
        'vendor_name',
        'contact_person',
        'phone_number',
        'email',
        'address',
        'registration_no',
        'status',
        'category'
    ];
}
