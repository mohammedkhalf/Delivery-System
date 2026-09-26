<?php

namespace App\Models;

use App\Traits\ReportFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProCustomerTransaction extends Model
{
    use HasFactory, ReportFilter;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id'         => 'integer',
        'subscription_id' => 'integer',
        'plan_id'         => 'integer',
        'amount'          => 'float',
        'plan_price'      => 'float',
        'order_count'     => 'integer',
        'start_at'        => 'datetime',
        'end_at'          => 'datetime',
        'paid_at'         => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscription()
    {
        return $this->belongsTo(ProCustomerSubscription::class, 'subscription_id');
    }

    public function plan()
    {
        return $this->belongsTo(ProCustomerSubscriptionPlan::class, 'plan_id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('payment_status', 'success');
    }
}
