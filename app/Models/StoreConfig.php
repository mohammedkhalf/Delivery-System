<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class StoreConfig extends Model
{
    use HasFactory;

    protected $table;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = Schema::hasTable('storeConfigs') ? 'storeConfigs' : 'store_configs';
    }

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'minimum_stock_for_warning' => 'integer',
        'show_low_stock_count' => 'boolean',
        'section_wise_ai_use_count' => 'integer',
        'image_wise_ai_use_count' => 'integer',
        'is_recommended' => 'boolean',
        'is_recommended_deleted' => 'boolean',
        'halal_tag_status' => 'boolean',
        'extra_packaging_status' => 'boolean',
        'extra_packaging_amount' => 'float',
        'verified_seller' => 'boolean',
        'has_seen_verified_badge_popup' => 'boolean',
        'can_edit_order' => 'boolean',
        'can_edit_booking' => 'boolean',
        'manage_service_setup' => 'boolean',
        'show_reviews_provider_panel' => 'boolean',
        'minimum_booking' => 'float',
        'instant_booking' => 'boolean',
        'repeat_booking' => 'boolean',
        'schedule_booking' => 'boolean',
        'choose_service_location' => 'array',
        'serviceman_can_cancel_booking' => 'boolean',
    ];

    public function Store()
    {
        return $this->belongsTo(Store::class);
    }
}
