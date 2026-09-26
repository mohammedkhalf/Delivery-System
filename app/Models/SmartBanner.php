<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class SmartBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'module_id',
        'active_days',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'position',
        'redirect_type',
        'redirect_target_id',
        'image',
        'status',
        'created_by',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'module_id' => 'integer',
        'status' => 'boolean',
        'redirect_target_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = ['image_full_url'];

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function storage(): MorphMany
    {
        return $this->morphMany(Storage::class, 'data');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getTitleAttribute(): ?string
    {
        return $this->translationValue('title') ?? null;
    }

    public function getSubtitleAttribute(): ?string
    {
        return $this->translationValue('subtitle') ?? null;
    }

    public function getImageFullUrlAttribute(): ?string
    {
        $disk = 'public';
        if ($this->relationLoaded('storage') && $this->storage) {
            foreach ($this->storage as $row) {
                if ($row->key === 'image') {
                    $disk = $row->value;
                    break;
                }
            }
        }
        return Helpers::get_full_url('smart-banner', $this->image, $disk);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopePosition($query, $position)
    {
        return $query->where('position', $position);
    }

    public static function datesOverlap(
        string $activeDaysA,
        ?string $startDateA,
        ?string $endDateA,
        string $activeDaysB,
        ?string $startDateB,
        ?string $endDateB,
        ?string $referenceDate = null
    ): bool {
        $today = $referenceDate ? \Carbon\Carbon::parse($referenceDate) : \Carbon\Carbon::today();
        $infinity = (clone $today)->addYears(100);

        $aStart = $activeDaysA === 'everyday' ? $today : \Carbon\Carbon::parse($startDateA);
        $aEnd = $activeDaysA === 'everyday' ? $infinity : \Carbon\Carbon::parse($endDateA);
        $bStart = $activeDaysB === 'everyday' ? $today : \Carbon\Carbon::parse($startDateB);
        $bEnd = $activeDaysB === 'everyday' ? $infinity : \Carbon\Carbon::parse($endDateB);

        return !($aEnd->lt($bStart) || $aStart->gt($bEnd));
    }

    public static function timesOverlap(
        ?string $startTimeA,
        ?string $endTimeA,
        ?string $startTimeB,
        ?string $endTimeB
    ): bool {
        $aStart = $startTimeA ? \Carbon\Carbon::parse($startTimeA) : \Carbon\Carbon::parse('00:00:00');
        $aEnd = $endTimeA ? \Carbon\Carbon::parse($endTimeA) : \Carbon\Carbon::parse('23:59:59');
        $bStart = $startTimeB ? \Carbon\Carbon::parse($startTimeB) : \Carbon\Carbon::parse('00:00:00');
        $bEnd = $endTimeB ? \Carbon\Carbon::parse($endTimeB) : \Carbon\Carbon::parse('23:59:59');

        return !($aEnd->lt($bStart) || $aStart->gt($bEnd));
    }

    protected function translationValue(string $key): ?string
    {
        if (!$this->relationLoaded('translations')) {
            return null;
        }
        $locale = app()->getLocale();
        foreach ($this->translations as $row) {
            if ($row->key === $key && $row->locale === $locale) {
                return $row->value;
            }
        }
        foreach ($this->translations as $row) {
            if ($row->key === $key) {
                return $row->value;
            }
        }
        return null;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ZoneScope);
        static::addGlobalScope('storage', function (Builder $builder) {
            $builder->with('storage');
        });
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($q) {
                $q->where('locale', app()->getLocale());
            }]);
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            Helpers::deleteCacheData('smart_banners_');

            if ($model->isDirty('image') && $model->image) {
                DB::table('storages')->updateOrInsert(
                    [
                        'data_type' => get_class($model),
                        'data_id' => $model->id,
                        'key' => 'image',
                    ],
                    [
                        'value' => Helpers::getDisk(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        static::created(function () {
            Helpers::deleteCacheData('smart_banners_');
        });

        static::updated(function () {
            Helpers::deleteCacheData('smart_banners_');
        });

        static::deleted(function () {
            Helpers::deleteCacheData('smart_banners_');
        });
    }
}
