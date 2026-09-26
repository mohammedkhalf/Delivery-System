<?php

namespace Modules\AI\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPreference extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'reference_id' => 'integer',
        'score' => 'float',
        'module_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
