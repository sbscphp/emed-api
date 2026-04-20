<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandlordAuditLog extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'landlord';
    protected $table = 'audit_logs';
    protected $with = ['causer'];

    protected $fillable = [
        'uuid',
        'causer_id',
        'action_type',
        'action_module',
        'action_id',
        'action',
        'log_name',
        'description',
        'module_accessed',
    ];

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function audit_log_transactions()
    {
        return $this->hasMany(AuditLogTransaction::class);
    }
}
