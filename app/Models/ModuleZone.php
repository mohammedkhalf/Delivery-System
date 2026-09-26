<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ModuleZone extends Pivot
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'zone_id',
        'per_km_shipping_charge',
        'minimum_shipping_charge',
        'maximum_shipping_charge',
        'maximum_cod_order_amount',
        'delivery_charge_type',
        'fixed_shipping_charge',
        'additional_delivery_option_status',
        'minimum_delivery_time',
        'minimum_delivery_charge',
    ];

    protected $casts = [
        'id'=>'integer',
        'module_id'=>'integer',
        'zone_id'=>'integer',
        'per_km_shipping_charge'=>'float',
        'minimum_shipping_charge'=>'float',
        'maximum_shipping_charge'=>'float',
        'maximum_cod_order_amount'=>'float',
        'fixed_shipping_charge'=>'float',
        'additional_delivery_option_status'=>'boolean',
        'minimum_delivery_time'=>'integer',
        'minimum_delivery_charge'=>'float',
    ];

    public function deliveryOptions(): HasMany
    {
        return $this->hasMany(ModuleZoneDeliveryOption::class, 'zone_id', 'zone_id')
            ->where('module_id', $this->getAttribute('module_id'));
    }

    public function getMinimumDeliveryTimePairAttribute(): array
    {
        return ModuleZoneDeliveryOption::minutesToPair((int) $this->getAttribute('minimum_delivery_time'));
    }
}
