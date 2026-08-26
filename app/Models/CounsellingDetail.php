<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounsellingDetail extends Model
{
    protected $connection = 'tenant';
    protected $guarded = ['id'];
}
