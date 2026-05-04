<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = ['name', 'status'];

    public function labParameters()
    {
        return $this->hasMany(LabParameter::class)->orderBy('display_order')->orderBy('id');
    }
}
