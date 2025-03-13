<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $fillable = ['name', 'type', 'address', 'state_id', 'active'];

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
