<?php

namespace Modules\ReelsModule\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelEngagement extends Model
{
    public const TYPE_VIEW = 'view';
    public const TYPE_LIKE = 'like';
    public const TYPE_VISIT = 'visit';
    public const TYPE_ORDER = 'order';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class, 'reel_id');
    }
}
