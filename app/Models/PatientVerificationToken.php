<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientVerificationToken extends Model
{
    use HasFactory;

    protected $connection = 'landlord';
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * A token is only good once, and only before it expires.
     */
    public function scopeUsable($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
