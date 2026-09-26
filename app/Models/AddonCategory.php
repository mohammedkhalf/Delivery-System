<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;

use App\Traits\GeneratesSlug;
use Modules\TaxModule\Entities\Taxable;

class AddonCategory extends Model
{
    use HasFactory, GeneratesSlug;

    protected $guarded = ['id'];
     protected $casts = [
        'module_id' => 'integer',
        'status' => 'integer',
    ];

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function Addons(): HasMany
    {
        return $this->hasMany(AddOn::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($category) {
            $category->slug = $category->generateSlug($category->name);
            $category->save();
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
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });
        return null;
    }
    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }


    
}
