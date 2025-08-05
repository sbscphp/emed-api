<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    use HasFactory;
    protected $connection = 'tenant';
    protected $guarded = ['id'];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicationInventories()
    {
        return $this->hasMany(MedicationInventory::class);
    }
}
