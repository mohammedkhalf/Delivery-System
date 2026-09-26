<?php

namespace App\Models;

use App\Traits\HandlesMissingAddonRelations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Rental\Entities\Trips;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use Modules\Service\Entities\ServiceBooking;

class OrderProDiscount extends Model
{
    use HandlesMissingAddonRelations, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'order_id'                            => 'integer',
        'trip_id'                             => 'integer',
        'ride_request_id'                     => 'integer',
        'service_booking_id'                  => 'integer',
        'user_id'                             => 'integer',
        'subscription_id'                     => 'integer',
        'plan_id'                             => 'integer',
        'transaction_id'                      => 'integer',
        'amount_saved'                        => 'float',
        'discount_percentage'                 => 'float',
        'max_discount_amount'                 => 'float',
        'min_order_amount'                    => 'float',
        'delivery_charge_discount_percentage' => 'float',
        'delivery_fee_reduction_amount'       => 'float',
        'original_delivery_charge'            => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function trip()
    {
        if (! addon_published_status('Rental')) {
            return $this->missingAddonRelation('trip_id');
        }

        return $this->belongsTo(Trips::class, 'trip_id');
    }

    public function rideRequest()
    {
        if (! addon_published_status('RideShare')) {
            return $this->missingAddonRelation('ride_request_id');
        }

        return $this->belongsTo(RideRequest::class, 'ride_request_id');
    }

    public function serviceBooking()
    {
        if (! service_addon_active()) {
            return $this->missingAddonRelation('service_booking_id');
        }

        return $this->belongsTo(ServiceBooking::class, 'service_booking_id');
    }

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

    public function transaction()
    {
        return $this->belongsTo(ProCustomerTransaction::class, 'transaction_id');
    }
}
