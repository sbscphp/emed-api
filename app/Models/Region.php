<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $connection = 'landlord';

    protected $table = 'regions';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'translations',
        'flag',
        'wikiDataId',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    public function countries()
    {
        return $this->hasMany(Country::class, 'region_id');
    }

}
