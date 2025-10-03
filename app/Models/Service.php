<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'services';

    public function visits()
    {
        return $this->hasMany(PatientVisit::class, 'service_id');
    }

    public function billing()
    {
        return $this->hasMany(BillingLog::class, 'service_type_id');
    }
}
