<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceUnit extends Model
{
    use HasFactory;
    protected $connection = 'tenant';
    protected $guarded = ['id'];

    protected $fillable = ['name'];

    public function billingLogs()
    {
        return $this->hasOne(BillingLog::class, "service_unit_id", "id");
    }
}
