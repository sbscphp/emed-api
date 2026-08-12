<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanPrice extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'landlord';
    protected $table = 'subscription_plan_prices';

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
