<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class VisitorLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $casts = [
        'visitor_log_id' => 'integer',
        'user_id' => 'integer',
        'visit_count' => 'integer',
        'order_count' => 'integer',
    ];

    public function visitor_log()
    {
        return $this->morphTo();
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function summary(string $type, ?int $userId = null): QueryBuilder
    {
        return DB::table('visitor_logs')
            ->select('visitor_log_id')
            ->selectRaw('SUM(visit_count) AS total_view_count')
            ->selectRaw('MAX(updated_at) AS last_viewed_at')
            ->where('visitor_log_type', $type)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->groupBy('visitor_log_id');
    }
}
