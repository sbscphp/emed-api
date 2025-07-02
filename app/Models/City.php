<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
protected $connection = 'landlord';
   
protected $table = 'cities';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'state_id',
        'state_code',
        'country_id',
        'country_code',
        'latitude',
        'longitude',
        'flag',
        'wikiDataId',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'flag' => 'boolean',
    ];

    // Relationships 
    public function state()
    {
        return $this->belongsTo(New_State::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
