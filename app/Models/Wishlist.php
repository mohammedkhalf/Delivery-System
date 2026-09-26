<?php

namespace App\Models;

use App\Traits\HandlesMissingAddonRelations;
use Illuminate\Database\Eloquent\Model;
use Modules\Service\Entities\Service;

class Wishlist extends Model
{
    use HandlesMissingAddonRelations;

    protected $fillable = [
        'user_id',
        'item_id',
        'store_id',
        'service_id',
    ];
    protected $casts = [
        'item_id' => 'integer',
        'user_id' => 'integer',
        'service_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    public function service()
    {
        if (! service_addon_active()) {
            return $this->missingAddonRelation('service_id');
        }

        return $this->belongsTo(Service::class);
    }
}
