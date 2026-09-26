<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyOrderReminder extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'remind_at'     => 'date',
        'notified_at'   => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeDueToday($q)
    {
        return $q->whereDate('remind_at', today());
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['pending', 'sent']);
    }
}
