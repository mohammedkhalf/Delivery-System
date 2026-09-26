<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Traits\HandlesMissingAddonRelations;
use App\Traits\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use App\Traits\GeneratesSlug;
use Modules\Service\Entities\Service;

class StoreCategory extends Model
{
    use HasFactory, ReportFilter, GeneratesSlug, HandlesMissingAddonRelations;

    protected $with = ['translations', 'storage'];

    protected $fillable = [
        'store_id',
        'module_id',
        'name',
        'slug',
        'image',
        'priority',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'module_id' => 'integer',
        'priority' => 'integer',
        'status' => 'integer',
    ];

    protected $appends = ['image_full_url'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'store_category_id');
    }

    public function services(): HasMany
    {
        if (! service_addon_active()) {
            return $this->missingAddonHasMany();
        }

        return $this->hasMany(Service::class, 'store_category_id');
    }

    public function storage(): MorphMany
    {
        return $this->morphMany(Storage::class, 'data');
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('category', $value, $storage['value']);
                }
            }
        }

        return Helpers::get_full_url('category', $value, 'public');
    }

    public function getNameAttribute($value): string
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'name') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($storeCategory) {
            $storeCategory->slug = $storeCategory->generateSlug($storeCategory->name);
            $storeCategory->save();
        });

        static::saved(function ($model) {
            if ($model->isDirty('image')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    protected static function booted()
    {
        static::addGlobalScope('storage', function (Builder $builder) {
            $builder->with('storage');
        });
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });

        static::saved(function () {
            Helpers::deleteCacheData('store_cat_items_');
        });

        static::deleted(function () {
            Helpers::deleteCacheData('store_cat_items_');
        });
    }
}
