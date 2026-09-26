<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use App\Scopes\StoreScope;
use App\Traits\GeneratesSlug;
use App\Traits\HasProductVideoPreview;
use App\Traits\ItemFilter;
use App\Traits\ReportFilter;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\TaxModule\Entities\Taxable;

class Item extends Model
{
    use HasFactory, ReportFilter, HasProductVideoPreview, GeneratesSlug, ItemFilter;
    protected $guarded = ['id'];
    protected $with = ['translations','storage','storeCategory'];
    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'status' => 'integer',
        'discount' => 'float',
        'avg_rating' => 'float',
        'set_menu' => 'integer',
        'category_id' => 'integer',
        'store_id' => 'integer',
        'store_category_id' => 'integer',
        'reviews_count' => 'integer',
        'recommended' => 'integer',
        'maximum_cart_quantity' => 'integer',
        'organic' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'veg' => 'integer',
        'images' => 'array',
        'module_id' => 'integer',
        'is_approved' => 'integer',
        'stock' => 'integer',
        "min_price" => 'float',
        "max_price" => 'float',
        'order_count' => 'integer',
        'rating_count' => 'integer',
        'unit_id' => 'integer',
        'is_halal' => 'integer',
    ];

    protected $appends = ['unit_type', 'image_full_url', 'images_full_url', 'video_full_url', 'video_size', 'video_preview_type', 'video_embed_url', 'video_preview_url', 'video_thumbnail_url', 'video_preview_modal_type', 'video_preview_modal_url', 'has_video_preview', 'has_video_source'];

    public function scopeRecommended($query)
    {
        return $query->where('recommended', 1);
    }

    public function scopeStoreCategory($query, $storeCategoryId)
    {
        return $query->when(is_numeric($storeCategoryId), function ($q) use ($storeCategoryId) {
            $q->where('store_category_id', $storeCategoryId);
        });
    }

    public function carts()
    {
        return $this->morphMany(Cart::class, 'item');
    }

    public function temp_product()
    {
        return $this->hasOne(TempProduct::class, 'item_id')->with('translations');
    }

    public function scopeDiscounted($query)
    {
        // return $query->where('discount','>',0);

        $nowDate = now()->format('Y-m-d');
        $nowTime = now()->format('H:i');

        return $query->where(function ($query) use ($nowDate, $nowTime) {
            $query->where('discount', '>', 0)
                ->orWhereHas('store.discount', function ($q) use ($nowDate, $nowTime) {
                    $q->whereDate('start_date', '<=', $nowDate)
                        ->whereDate('end_date', '>=', $nowDate)
                        ->whereTime('start_time', '<=', $nowTime)
                        ->whereTime('end_time', '>=', $nowTime);
                })
                ->orWhereHas('flashSaleItems.flashSale', function ($q) use ($nowDate, $nowTime) {
                    $q->where('is_publish', 1)
                        ->whereDate('start_date', '<=', $nowDate)
                        ->whereDate('end_date', '>=', $nowDate);
                });
        });
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }


    public function scopeActive($query , $zone_ids = null ,$module_id = null)
    {
        $module_id = $module_id && is_numeric($module_id) ? $module_id : null;
        $current_module_data = config('module.current_module_data');
        $zone_module_id = $module_id ?? ($current_module_data['id'] ?? null);

        return $query
        ->where('status', 1)->where('is_approved', 1)
            ->when($module_id, function ($query) use ($module_id) {
                $query->where('module_id', $module_id);
            })
            ->whereHas('store', function ($query) use ($zone_ids, $zone_module_id) {
                $query->where('status', 1)
                    ->where(function ($query) {
                        $query->where('store_business_model', 'commission')
                            ->orWhereHas('store_sub', function ($query) {
                                $query->where('max_order', 'unlimited')->orWhere('max_order', '>', 0);
                            });
                    })
                    ->when($zone_ids && is_array($zone_ids), function ($query) use ($zone_ids, $zone_module_id) {
                        $query->whereIn('zone_id', $zone_ids)
                            ->whereHas('zone.modules', function ($query) use ($zone_module_id) {
                                $query->when($zone_module_id, function ($query) use ($zone_module_id) {
                                    $query->where('modules.id', $zone_module_id);
                                });
                            });
                    });
            })
            ->whereHas('module', function ($query) {
                $query->where('status', 1);
            })
            ->whereHas('category', function ($q) {
                $q->where('status', 1)
                    ->where(function ($q) {
                        $q->where('parent_id', 0)
                            ->orWhereHas('parent', fn ($p) => $p->where('status', 1));
                    });
            });
    }
    public function scopePopular($query)
    {
        return $query->orderBy('order_count', 'desc');
    }
    public function scopeApproved($query)
    {
        return $query->where('is_approved', 1);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function whislists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    // public function scopeHasRunningFlashSale($query)
    // {
    //     return $query->whereHas('flashSaleItems', function ($query) {
    //         $query->whereHas('flashSale', function ($query) {
    //             $query->Running();
    //         });
    //     });
    // }

        public function rating()
    {
        return $this->hasMany(Review::class, 'item_id')
            ->select(
                'item_id',
                DB::raw('AVG(rating) as average'),
                DB::raw('COUNT(*) as rating_count'),
                DB::raw('COUNT(CASE WHEN comment IS NOT NULL THEN 1 END) as review_count')
            )
            ->groupBy('item_id');
    }

    public function flashSaleItems()
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function getUnitTypeAttribute()
    {
        return $this->unit ? $this->unit->unit : null;
    }

    public function getNameAttribute($value)
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

    public function getDescriptionAttribute($value)
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'description') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }
    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('product', $value, $storage['value'],'default');
                }
            }
        }

        return Helpers::get_full_url('product', $value, 'public');
    }
    public function getImagesFullUrlAttribute()
    {
        $images = [];
        $value = is_array($this->images)
            ? $this->images
            : ($this->images && is_string($this->images) && $this->isValidJson($this->images)
                ? json_decode($this->images, true)
                : []);
        if ($value) {
            foreach ($value as $item) {
                $item = is_array($item) ? $item : (is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true) : ['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('product', $item['img'], $item['storage'],'default');
            }
        }

        return $images;
    }

    private function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }


    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function storeCategory()
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    public function pharmacy_item_details()
    {
        return $this->hasOne(PharmacyItemDetails::class, 'item_id');
    }
    public function ecommerce_item_details()
    {
        return $this->hasOne(EcommerceItemDetails::class, 'item_id');
    }

    public function orders()
    {
        return $this->hasMany(OrderDetail::class);
    }

    protected static function booted()
    {
        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            static::addGlobalScope(new StoreScope);
        }

        static::addGlobalScope(new ZoneScope);
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
    }


    public function scopeType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('veg', true);
        } else if ($type == 'non_veg') {
            return $query->where('veg', false);
        }
        return $query;
    }

    public function scopeAvailable($query, $time)
    {
        $query->where(function ($q) use ($time) {
            $q->where('available_time_starts', '<=', $time)->where('available_time_ends', '>=', $time);
        });
    }
    public function scopeUnAvailable($query, $time)
    {
        $query->whereNot(function ($q) use ($time) {
            $q->where('available_time_starts', '<=', $time)->where('available_time_ends', '>=', $time);
        });
    }

    public function getIsAvailableNowAttribute(): bool
    {
        $start = $this->available_time_starts;
        $end = $this->available_time_ends;
        if (empty($start) || empty($end)) {
            return true;
        }
        $now = now()->format('H:i:s');
        return $start <= $end
            ? ($now >= $start && $now <= $end)
            : ($now >= $start || $now <= $end);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['store_category_id']) && is_numeric($filters['store_category_id']), function ($q) use ($filters) {
                $q->where('store_category_id', $filters['store_category_id']);
            })
            ->when(isset($filters['filter_by']) && is_array($filters['filter_by']), function ($q) use ($filters) {
                foreach ($filters['filter_by'] as $item) {
                    if ($item == 'free_delivery') {
                        $q->whereHas('store', function ($query) {
                            $query->where('free_delivery', 1);
                        });
                    } elseif ($item == 'discounted' || $item == 'offers') {
                        $q->discounted();
                    } elseif ($item == 'popular') {
                        $q->reorder()->orderBy('order_count', 'desc');
                    } elseif ($item == 'new_arrivals') {
                        $q->reorder()->latest();
                    } elseif ($item == 'top_rated') {
                        $q->where('avg_rating', '>', 0)->reorder()->orderBy('avg_rating', 'desc');
                    } elseif ($item == 'veg') {
                        $q->where('veg', 1);
                    } elseif ($item == 'non_veg') {
                        $q->where('veg', 0);
                    } elseif ($item == 'currently_available') {
                        $q->available(now()->format('H:i:s'));
                    } elseif ($item == 'halal') {
                        $q->where('is_halal', 1);
                    } elseif ($item == 'high') {
                        $q->reorder()->orderBy('price', 'desc');
                    } elseif ($item == 'low') {
                        $q->reorder()->orderBy('price', 'asc');
                    } elseif ($item == 'nearby') {
                        $longitude = request()->header('longitude') ?? request('longitude');
                        $latitude = request()->header('latitude') ?? request('latitude');
                        if ($longitude && $latitude) {
                            $q->selectSub(function ($query) use ($longitude, $latitude) {
                                $query->selectRaw('ST_Distance_Sphere(point(longitude, latitude), point(?, ?))', [$longitude, $latitude])
                                    ->from('stores')->whereColumn('stores.id', 'items.store_id')->limit(1);
                            }, 'store_distance')->reorder()->orderBy('store_distance', 'asc');
                        }
                    } elseif ($item == 'verified_seller') {
                        $q->whereHas('store.storeConfig', function ($query) {
                            $query->where('verified_seller', 1);
                        });
                    }
                }
            });
    }

    public function scopeApplySorting($query, $sortBy)
    {
        $sortBy = self::normalizeSortValue($sortBy);

        return $query->when($sortBy && $sortBy !== 'default', function ($q) use ($sortBy) {
            if ($sortBy == 'fast_delivery') {
                $q->reorder()->orderBy(function ($query) {
                    $query->selectRaw('IF(((select count(*) from `store_schedule` where `stores`.`id` = `store_schedule`.`store_id` and `store_schedule`.`day` = '.now()->dayOfWeek.' and `store_schedule`.`opening_time` < "'.now()->format('H:i:s').'" and `store_schedule`.`closing_time` >"'.now()->format('H:i:s').'") > 0), true, false)')
                        ->from('stores')->whereColumn('stores.id', 'items.store_id')->limit(1);
                }, 'desc')
                ->orderBy(function ($query) {
                    $query->selectRaw("
                        CASE
                            WHEN delivery_time LIKE '%hour%'
                                THEN CAST(SUBSTRING_INDEX(delivery_time,'-',1) AS UNSIGNED) * 60
                            WHEN delivery_time LIKE '%min%'
                                THEN CAST(SUBSTRING_INDEX(delivery_time,'-',1) AS UNSIGNED)
                            ELSE CAST(SUBSTRING_INDEX(delivery_time,'-',1) AS UNSIGNED)
                        END")
                        ->from('stores')->whereColumn('stores.id', 'items.store_id')->limit(1);
                }, 'asc');
            } elseif ($sortBy == 'a_to_z') {
                $q->reorder()->orderBy('name', 'asc');
            } elseif ($sortBy == 'z_to_a') {
                $q->reorder()->orderBy('name', 'desc');
            } elseif ($sortBy == 'price_low_to_high') {
                $q->reorder()->orderBy('price', 'asc');
            } elseif ($sortBy == 'price_high_to_low') {
                $q->reorder()->orderBy('price', 'desc');
            } elseif ($sortBy == 'distance') {
                $longitude = request()->header('longitude') ?? request('longitude');
                $latitude = request()->header('latitude') ?? request('latitude');

                if ($longitude && $latitude) {
                    $q->reorder()
                        ->selectSub(function ($query) use ($longitude, $latitude) {
                            $query->selectRaw(
                                'ST_Distance_Sphere(point(longitude, latitude), point(?, ?))',
                                [$longitude, $latitude]
                            )
                            ->from('stores')
                            ->whereColumn('stores.id', 'items.store_id')
                            ->limit(1);
                        }, 'distance')
                        ->orderBy('distance', 'asc');
                }
            } elseif ($sortBy == 'high_rated') {
                $q->reorder()->orderBy('avg_rating', 'desc');
            }
        });
    }

    public function scopeApplyRating($query, $request)
    {
        if (!$request) {
            return $query;
        }

        $ratingPlus = $request->rating_plus ?? null;
        if ($ratingPlus && !is_array($ratingPlus)) {
            $ratingPlus = str_getcsv(trim($ratingPlus, "[]"), ',');
        }
        $ratingPlus = is_array($ratingPlus)
            ? array_values(array_filter(array_map('intval', $ratingPlus), fn ($v) => $v > 0))
            : [];

        return $query->when($request->rating == 1, function ($query) {
            return $query->has('reviews')->withCount('reviews')->orderBy('reviews_count', 'desc');
        })
        ->when(!empty($ratingPlus), function ($query) use ($ratingPlus) {
            $query->where('avg_rating', '>=', min($ratingPlus));
        })
        ->when($request->rating_count, function ($query) use ($request) {
            $query->where('avg_rating', '>=', $request->rating_count);
        })
        ->when(($request->rating_1 == 1 || $request->rating_1_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 1);
        })
        ->when(($request->rating_2 == 1 || $request->rating_2_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 2);
        })
        ->when(($request->rating_3 == 1 || $request->rating_3_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 3);
        })
        ->when(($request->rating_4 == 1 || $request->rating_4_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 4);
        })
        ->when($request->rating_3_plus == 1, function ($query) {
            $query->where('avg_rating', '>', 3);
        })
        ->when(($request->rating_4_plus == 1 && !($request->rating_5 == 1 || $request->rating_3_plus == 1) || ($request->rating_4_plus == 1 && $request->rating_5 == 1 && $request->rating_3_plus != 1)), function ($query) {
            $query->where('avg_rating', '>', 4);
        })
        ->when($request->rating_5 == 1 && !($request->rating_4_plus == 1 || $request->rating_3_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 5);
        });
    }

    public function scopeApplyPriceRange($query, $request)
    {
        if (!$request) {
            return $query;
        }

        $price = $request->price ?? null;
        if (is_string($price)) {
            $price = str_replace(['[', ']'], '', $price);
            $price = explode(',', $price);
        }

        return $query->when($price && count($price) == 2 && is_numeric($price[0]) && is_numeric($price[1]), function ($query) use ($price) {
            $query->whereBetween('price', [round($price[0], 2), round($price[1], 2)]);
        })->when($request->min_price, function ($query) use ($request) {
            $query->where('price', '>=', $request->min_price);
        })->when($request->max_price, function ($query) use ($request) {
            $query->where('price', '<=', $request->max_price);
        });
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    public function allergies()
    {
        return $this->belongsToMany(Allergy::class);
    }
    public function generic()
    {
        return $this->belongsToMany(GenericName::class, 'item_generic_names');
    }
    public function nutritions()
    {
        return $this->belongsToMany(Nutrition::class);
    }
    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }
    protected static function boot()
    {
        parent::boot();
        static::created(function ($item) {
            $item->slug = $item->generateSlug($item->name);
            $item->save();
        });
        static::saved(function ($model) {
            $offerFields = ['discount', 'discount_type', 'status', 'is_approved', 'price', 'store_id', 'module_id'];
            foreach ($offerFields as $field) {
                if ($model->isDirty($field)) {
                    self::flushOfferFeaturedCache();
                    break;
                }
            }
        });
        static::deleted(fn () => self::flushOfferFeaturedCache());

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
            if ($model->isDirty('images')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'images',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($model->isDirty('video')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'video',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public static function flushOfferFeaturedCache(): void
    {
        try {
            $keys = DB::table('cache')->where('key', 'like', '%offer.featured.%')->pluck('key');
            $appName = strtolower(str_replace('=', '', (string) env('APP_NAME').'_cache'));
            foreach ($keys as $key) {
                \Illuminate\Support\Facades\Cache::forget(str_replace($appName, '', $key));
            }
        } catch (\Throwable) {
        }
    }

    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

    public function seoData(){
        return $this->hasOne(ItemSeoData::class,'item_id');
    }


    public function users()
    {
        return $this->morphToMany(User::class ,'visitor_log');
    }
}
