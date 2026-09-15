<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Theme extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'landlord';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
