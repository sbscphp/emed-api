<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class New_State extends Model
{
    protected $connection = 'landlord';
    
     protected $table = 'states';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'country_id',
        'country_code',
        'fips_code',
        'iso2',
        'type',
        'level',
        'parent_id',
        'latitude',
        'longitude',
        'flag',
        'wikiDataId',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'flag' => 'boolean',
        'level' => 'integer',
    ];

    // Relationships
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

}
