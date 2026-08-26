<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationRecommendation extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
}
