<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An imaging modality — X-ray, ultrasound, CT, MRI, mammography, fluoroscopy.
 */
class RadiologyCategory extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function radiologyServices()
    {
        return $this->hasMany(RadiologyService::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
