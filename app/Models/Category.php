<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Traits\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\GeneratesSlug;
use Modules\TaxModule\Entities\Taxable;

/**
 * Class Category
 *
 * @property int $parent_id
 * @property int $position
 * @property int $priority
 * @property int $status
 * @property int $featured
 * @property int $module_id
 * @property int $products_count
 * @property int $childes_count
 * @property mixed $translations
 *
 * @package App\Models
 */
class Category extends Model
{
    use HasFactory, ReportFilter, GeneratesSlug;

    protected $with=['translations','storage'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'position',
        'priority',
        'status',
        'featured',
        'module_id',
        'products_count',
        'childes_count',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'position' => 'integer',
        'priority' => 'integer',
        'status' => 'integer',
        'featured' => 'integer',
        'module_id' => 'integer',
        'products_count' => 'integer',
        'childes_count' => 'integer',
    ];
    protected $appends = ['image_full_url'];

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', '=', 1);
    }

    public function childes(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    /**
     * Sibling-scoped name uniqueness for the shared categories table.
     * Main categories (parent_id = 0) must be unique within a module; a sub category
     * must be unique within its parent — but the same sub-category name may be reused
     * under different parents. Matching is on the default (categories.name) value.
     */
    /**
     * Resolve the default-language name from the submitted name[]/lang[] arrays.
     */
    public static function defaultName($names, $langs): ?string
    {
        if (! is_array($names)) {
            return null;
        }
        $index = is_array($langs) ? array_search('default', $langs) : false;
        $name = $index !== false ? ($names[$index] ?? null) : ($names[0] ?? null);

        return ($name === null || trim($name) === '') ? null : $name;
    }

    public static function isDuplicateName(string $name, int $moduleId, int $parentId, ?int $ignoreId = null): bool
    {
        return static::withoutGlobalScopes()
            ->where('module_id', $moduleId)
            ->where('parent_id', $parentId)
            ->where('name', trim($name))
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
    public function getImageFullUrlAttribute(){
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('category',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('category',$value,'public');
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($category) {
            $category->slug = $category->generateSlug($category->name);
            $category->save();
        });
        static::saved(function ($model) {
            if($model->isDirty('image')){
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

    protected static function booted(): Builder|null
    {
        static::addGlobalScope('storage', function ($builder) {
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
        return null;
    }
    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }
}
