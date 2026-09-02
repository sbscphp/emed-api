<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadiologyService extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * The imaging modality this service is performed under.
     */
    public function radiologyCategory()
    {
        return $this->belongsTo(RadiologyCategory::class);
    }

    public function serviceUnit()
    {
        return $this->belongsTo(ServiceUnit::class);
    }
}
