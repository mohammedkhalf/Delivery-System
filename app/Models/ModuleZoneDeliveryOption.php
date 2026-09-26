<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleZoneDeliveryOption extends Model
{
    use HasFactory;

    protected $table = 'module_zone_delivery_options';

    protected $fillable = [
        'module_id',
        'zone_id',
        'delivery_type',
        'extra_charge',
        'reduce_charge',
        'add_delivery_time',
        'reduce_delivery_time',
    ];

    protected $casts = [
        'id'                   => 'integer',
        'module_id'            => 'integer',
        'zone_id'              => 'integer',
        'extra_charge'         => 'float',
        'reduce_charge'        => 'float',
        'add_delivery_time'    => 'integer',
        'reduce_delivery_time' => 'integer',
    ];

    public const TYPE_STANDARD       = 'standard';
    public const TYPE_EXPRESS        = 'express';
    public const TYPE_SLIGHTLY_DELAY = 'slightly_delay';

    public const TYPES = [
        self::TYPE_STANDARD,
        self::TYPE_EXPRESS,
        self::TYPE_SLIGHTLY_DELAY,
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function scopeFor(Builder $query, int $moduleId, int $zoneId): Builder
    {
        return $query->where('module_id', $moduleId)->where('zone_id', $zoneId);
    }

    public function getAddDeliveryTimeAttribute($value): array
    {
        return self::minutesToPair((int) $value);
    }

    public function setAddDeliveryTimeAttribute($value): void
    {
        $this->attributes['add_delivery_time'] = self::pairToMinutes($value);
    }

    public function getReduceDeliveryTimeAttribute($value): array
    {
        return self::minutesToPair((int) $value);
    }

    public function setReduceDeliveryTimeAttribute($value): void
    {
        $this->attributes['reduce_delivery_time'] = self::pairToMinutes($value);
    }

    public static function minutesToPair(int $minutes): array
    {
        if ($minutes >= 60 && $minutes % 60 === 0) {
            return ['value' => intdiv($minutes, 60), 'unit' => 'hour', 'minutes' => $minutes];
        }
        return ['value' => $minutes, 'unit' => 'min', 'minutes' => $minutes];
    }

    public static function pairToMinutes(mixed $value): int
    {
        if (is_array($value)) {
            $count = (int) ($value['value'] ?? 0);
            $unit  = $value['unit'] ?? 'min';
            return $unit === 'hour' ? $count * 60 : $count;
        }
        return max(0, (int) $value);
    }
}
