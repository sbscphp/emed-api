<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacyRequest extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
