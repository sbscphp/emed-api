<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subregions extends Model
{
    protected $table = 'subregions';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'translations',
        'region_id',
        'flag',
        'wikiDataId',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    // Relationships
    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function countries()
    {
        return $this->hasMany(Country::class, 'subregion_id');
    }
}
