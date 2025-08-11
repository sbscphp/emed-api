<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DosageAdministration extends Model
{
    protected $connection = 'tenant';
    protected $guarded = ['id'];
}
