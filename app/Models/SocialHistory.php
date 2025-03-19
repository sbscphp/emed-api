<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialHistory extends Model
{
    use HasFactory;

    protected $connection = 'tenant';
    protected $fillable = ['patient_id','name','status','duration','consultation_id','visitno'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
