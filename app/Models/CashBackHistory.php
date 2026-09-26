<?php

namespace App\Models;

use App\Traits\HandlesMissingAddonRelations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Rental\Entities\Trips;
use Modules\Service\Entities\ServiceBooking;

class CashBackHistory extends Model
{
    use HandlesMissingAddonRelations, HasFactory;

    protected $guarded = ['id'];
    protected $casts = [
        'same_user_limit' => 'integer',
        'total_used' => 'integer',
        'cashback_amount' => 'float',
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'status' => 'boolean',
    ];



    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }
    public function trip()
    {
        if (! addon_published_status('Rental')) {
            return $this->missingAddonRelation('trip_id');
        }

        return $this->belongsTo(Trips::class,'trip_id');
    }
    public function serviceBooking()
    {
        if (! service_addon_active()) {
            return $this->missingAddonRelation('service_booking_id');
        }

        return $this->belongsTo(ServiceBooking::class,'service_booking_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function cashBack()
    {
        return $this->belongsTo(CashBack::class,'cash_back_id');
    }
}
