<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLogTransaction extends Model
{
    protected $guarded = ['id'];

    public function auditLog()
    {
        return $this->belongsTo(AuditLog::class);
    }
}

