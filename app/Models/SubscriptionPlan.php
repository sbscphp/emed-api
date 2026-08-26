<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $connection = 'landlord';
    protected $table = 'subscription_plans';

    protected $casts = [
        'status' => 'string',
    ];

    public function prices()
    {
        return $this->hasMany(SubscriptionPlanPrice::class, 'subscription_plan_id');
    }
}
