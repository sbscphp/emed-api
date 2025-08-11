<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dosage_Adminstration extends Model
{
    protected $connection = 'tenant';
    protected $guarded = ['id'];
}
