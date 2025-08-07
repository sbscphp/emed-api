<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation_Details_Treatment extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'consultation__details__treatments';
}
