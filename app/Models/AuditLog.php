<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $with = ['causer'];

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }


    public function audit_log_transactions()
    {
        return $this->hasMany(AuditLogTransaction::class);
    }
}
