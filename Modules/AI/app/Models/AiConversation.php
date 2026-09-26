<?php

namespace Modules\AI\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_id',
        'module_id',
        'zone_id',
        'title',
        'status',
    ];

    protected $casts = [
        'user_id'   => 'integer',
        'module_id' => 'integer',
        'zone_id'   => 'integer',
    ];

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
