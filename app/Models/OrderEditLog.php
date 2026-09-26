<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEditLog extends Model
{
    protected $fillable = ['order_id', 'log', 'edited_by'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
