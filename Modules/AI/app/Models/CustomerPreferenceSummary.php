<?php

namespace Modules\AI\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPreferenceSummary extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'module_id' => 'integer',
        'top_items' => 'array',
        'top_categories' => 'array',
        'top_stores' => 'array',
        'ai_keywords' => 'array',
        'keyword_item_ids' => 'array',
        'keyword_category_ids' => 'array',
        'keyword_store_ids' => 'array',
        'update_count' => 'integer',
        'last_rebuilt_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
