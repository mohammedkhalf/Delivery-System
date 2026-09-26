<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProCustomerBenefitSetting extends Model
{
    protected $table = 'pro_customer_benefit_settings';

    protected $fillable = ['benefit_type', 'module_type', 'settings'];

    protected $casts = ['settings' => 'array'];

    const DISCOUNT_MODULE_TYPES = [
        'grocery', 'food', 'ecommerce', 'pharmacy', 'ride-share', 'rental', 'service',
    ];

    const DELIVERY_FEE_MODULE_TYPES = [
        'grocery', 'food', 'ecommerce', 'pharmacy', 'parcel',
    ];

    public static function upsertForModule(string $benefitType, ?string $moduleType, array $settings): void
    {
        static::updateOrCreate(
            ['benefit_type' => $benefitType, 'module_type' => $moduleType],
            ['settings' => $settings]
        );
    }

    public static function getSettings(string $benefitType, ?string $moduleType): array
    {
        return static::where('benefit_type', $benefitType)
            ->where('module_type', $moduleType)
            ->value('settings') ?? [];
    }

    public static function getAllForBenefit(string $benefitType): \Illuminate\Support\Collection
    {
        return static::where('benefit_type', $benefitType)->get()->keyBy('module_type');
    }
}
