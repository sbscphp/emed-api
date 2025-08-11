<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Immunization extends Model
{
    protected $connection = 'tenant';
    protected $guarded = ['id'];
}
