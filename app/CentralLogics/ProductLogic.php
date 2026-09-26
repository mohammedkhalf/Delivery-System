<?php

namespace App\CentralLogics;

use App\Models\Item;
use App\Models\Review;
use App\Models\Store;
use App\Models\Category;
use App\Models\FlashSaleItem;
use App\Traits\ItemFilter;
use Illuminate\Support\Facades\DB;


class ProductLogic
{
    use ItemFilter;

    public static function get_product($id)
    {
        return Item::active(module_id: config('module.current_module_data')['id'] ?? null)
        ->when(is_numeric($id),function ($qurey) use($id){
            $qurey-> where('id', $id);
        })
        ->when(!is_numeric($id),function ($qurey) use($id){
            $qurey-> where('slug', $id);
        })
        ->first();
    }

    public static function get_latest_products($zone_id, $limit, $offset, $store_id, $category_id, $type, $min=false, $max=false, $product_id=null, $filter = null, $rating_count = null, $store_category_id = null, $user_id = null)
    {
        // info($filter);

        $latest_items_default_status = 1;
        // $latest_items_default_status =BusinessSetting::where('key', 'latest_items_default_status')->first()?->value ?? 1;
        $latest_items_sort_by_general = Helpers::getPriorityList(name: 'latest_items_sort_by_general', type: 'general');
        $latest_items_sort_by_unavailable = Helpers::getPriorityList(name: 'latest_items_sort_by_unavailable', type: 'unavailable');
        $latest_items_sort_by_temp_closed = Helpers::getPriorityList(name: 'latest_items_sort_by_temp_closed', type: 'temp_closed');
        $zones = !empty($zone_id) ? json_decode($zone_id, true) : null;


        if($category_id != 0){
            $category_id = explode(',', $category_id);
        }
        if($min == false){
            $min = 0.00000001;
        }

        $query = Item::
        when($category_id != 0, function($q)use($category_id){
            $q->whereHas('category',function($q)use($category_id){
                return $q->whereIn('id',$category_id)->orWhereIn('parent_id', $category_id);
            });
        })
        ->when(is_numeric($store_category_id), function($q)use($store_category_id){
            $q->where('store_category_id', $store_category_id);
        })
        ->when(isset($product_id), function($q)use($product_id){
            $q->where('id', '!=', $product_id);
        })

        ->when(empty($store_id), function ($q) use ($zones) {
                $q->whereHas('store', function ($query) use ($zones) {

                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('module_id', config('module.current_module_data')['id'])
                            ->whereHas('zone.modules', function ($query) {
                                $query->where('modules.id', config('module.current_module_data')['id']);
                            });
                    });

                    $query->when(!empty($zones), function ($query) use ($zones) {
                        $query->whereIn('zone_id', $zones);
                    });
                });
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->when(is_numeric($store_id),function ($qurey) use($store_id){
            $qurey->where('store_id', $store_id);
        })
        ->when(!is_numeric($store_id), function ($query) use ($store_id) {
            $query->whereHas('store', function ($q) use ($store_id) {
                $q->where('slug', $store_id);
            });
        })

        ->select(['items.*'])
        ->selectSub(function ($subQuery) {
            $subQuery->selectRaw('active as temp_available')
                ->from('stores')
                ->whereColumn('stores.id', 'items.store_id');
        }, 'temp_available')
        ->active()->type($type)

        ->when($filter && in_array('popular', $filter), function ($qurey) {
            $qurey->popular();
        })
        ->when($filter && in_array('high', $filter), function ($qurey) {
            $qurey->orderBy('price', 'DESC');
        })
        ->when($filter && in_array('low', $filter), function ($qurey) {
            $qurey->orderBy('price', 'asc');
        })
        ->when($filter && in_array('discounted', $filter), function ($qurey) {
            $qurey->Discounted();
        })
        ->when($rating_count, function($query) use ($rating_count){
            $query->where('avg_rating', '>=' , $rating_count);
        })
        ->when($filter && in_array('available_now', $filter), function ($query) {
            $query->where(function ($q) {
                $currentTime = now()->format('H:i:s');
                $q->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
                ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
            });
        });

        if ($latest_items_default_status == '1'){
            $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);
            $query = $query->latest();
        } else {
            if(config('module.current_module_data')['module_type']  !== 'food'){
                if($latest_items_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($latest_items_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }

            }

            if($latest_items_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($latest_items_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            }
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);


        $query = Item::
        when($category_id != 0, function($q)use($category_id){
            $q->whereHas('category',function($q)use($category_id){
                return $q->whereId($category_id)->orWhere('parent_id', $category_id);
            });
        })
        ->when(isset($product_id), function($q)use($product_id){
            $q->where('id', '!=', $product_id);
        })
            ->when(empty($store_id), function ($q) use ($zones) {

                $q->when(!empty($zones), function ($q) use ($zones) {
                    $q->whereHas('module.zones', function ($query) use ($zones) {
                        $query->whereIn('zones.id', $zones);
                    });
                });

                $q->whereHas('store', function ($query) use ($zones) {

                    $query->when(config('module.current_module_data'), function ($query) {
                        $query->where('module_id', config('module.current_module_data')['id'])
                            ->whereHas('zone.modules', function ($query) {
                                $query->where('modules.id', config('module.current_module_data')['id']);
                            });
                    });

                    $query->when(!empty($zones), function ($query) use ($zones) {
                        $query->whereIn('zone_id', $zones);
                    });
                });
            })

        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->when(is_numeric($store_id),function ($qurey) use($store_id){
            $qurey->where('store_id', $store_id);
        })
        ->when(!is_numeric($store_id), function ($query) use ($store_id) {
            $query->whereHas('store', function ($q) use ($store_id) {
                return $q->where('slug', $store_id);
            });
        })

        ->select(['items.*'])
        ->selectSub(function ($subQuery) {
            $subQuery->selectRaw('active as temp_available')
                ->from('stores')
                ->whereColumn('stores.id', 'items.store_id');
        }, 'temp_available')
        ->active()->type($type);

        if ($latest_items_default_status == '1'){
            $query = $query->latest();
        } else {
            if(config('module.current_module_data')['module_type']  !== 'food'){
                if($latest_items_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($latest_items_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if($latest_items_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($latest_items_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            }
        }



        $item_categories = $query->pluck('category_id')->toArray();

        $item_categories = array_unique($item_categories);

        $categories = Category::withCount(['products','childes'])->with(['childes' => function($query)  {
            $query->withCount(['products','childes']);
        }])
        ->where(['position'=>0,'status'=>1])
        ->when(config('module.current_module_data'), function($query){
            $query->module(config('module.current_module_data')['id']);
        })
        ->whereIn('id',$item_categories)
        ->orderBy('priority','desc')->get();


        $prices = Item::active()
            ->when(is_numeric($store_id), fn($q) => $q->where('store_id', $store_id))
            ->when(!is_numeric($store_id), fn($q) =>
                $q->whereHas('store', fn($q2) => $q2->where('slug', $store_id))
            )
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $min_price = $prices->min_price;
        $max_price = $prices->max_price;


        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories'=>$categories,
            'min_price' => $min_price,
            'max_price' => $max_price
        ];
    }

    public static function get_new_products($zone_id, $type, $min=false, $max=false,$product_id=null,$limit = null, $offset = null, $filter = null, $rating_count = null, $category_ids = null, $brand_ids = null, $store_category_id = null, $user_id = null)
    {

        $latest_items_default_status = 1;
        // $latest_items_default_status =BusinessSetting::where('key', 'latest_items_default_status')->first()?->value ?? 1;
        $latest_items_sort_by_general = Helpers::getPriorityList(name: 'latest_items_sort_by_general', type: 'general');
        $latest_items_sort_by_unavailable = Helpers::getPriorityList(name: 'latest_items_sort_by_unavailable', type: 'unavailable');
        $latest_items_sort_by_temp_closed = Helpers::getPriorityList(name: 'latest_items_sort_by_temp_closed', type: 'temp_closed');

        $category_ids = isset($category_ids)?(is_array($category_ids)?$category_ids:json_decode($category_ids)):[];
        $brand_ids = isset($brand_ids)?(is_array($brand_ids)?$brand_ids:json_decode($brand_ids)):[];
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $query = Item::
        when(isset($product_id), function($q)use($product_id){
            $q->where('id', '!=', $product_id);
        })
        ->when(isset($category_ids) && (count($category_ids)>0), function($query)use($category_ids){
            $query->whereHas('category',function($q)use($category_ids){
                return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
            });
        })
        ->when(is_numeric($store_category_id), function($query)use($store_category_id){
            $query->where('store_category_id', $store_category_id);
        })
        ->when(isset($brand_ids) && (count($brand_ids)>0), function($query)use($brand_ids){
            $query->whereHas('ecommerce_item_details',function($q)use($brand_ids){
                return $q->whereHas('brand',function($q)use($brand_ids){
                    return $q->whereIn('id',$brand_ids);
                });
            });
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id , $filter){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true))
            ->when($filter && in_array('free_delivery',$filter),function ($qurey){
                return $qurey->where('free_delivery',1);
            })

            ->when($filter && in_array('coupon',$filter),function ($qurey){
                return $qurey->has('activeCoupons');
            });
        })
        ->when($rating_count, function($query) use ($rating_count){
            $query->where('avg_rating', '>=' , $rating_count);
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->when($filter && in_array('top_rated',$filter),function ($qurey){
            $qurey->withCount('reviews')->orderBy('reviews_count','desc');
        })
        ->when($filter && in_array('popular',$filter),function ($qurey){
            $qurey->popular();
        })
        ->when($filter && in_array('high',$filter),function ($qurey){
            $qurey->orderBy('price', 'desc');
        })
        ->when($filter && in_array('low',$filter),function ($qurey){
            $qurey->orderBy('price', 'asc');
        })
        ->when($filter && in_array('discounted',$filter),function ($qurey){
            $qurey->Discounted()->orderBy('discount','desc');
        })
       ->when($filter && in_array('available_now', $filter), function ($query) {
            $query->where(function ($q) {
                $currentTime = now()->format('H:i:s');
                $q->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
                ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
            });
        })

        ->select(['items.*'])
        ->selectSub(function ($subQuery) {
            $subQuery->selectRaw('active as temp_available')
                ->from('stores')
                ->whereColumn('stores.id', 'items.store_id');
        }, 'temp_available')
        ->active()->type($type);

        if ($latest_items_default_status == '1'){
            $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);
            $query = $query->latest();
        } else {
            if(config('module.current_module_data')['module_type']  !== 'food'){
                if($latest_items_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($latest_items_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if($latest_items_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($latest_items_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            }
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        $item_categories = collect($paginator->items())->pluck('category_id')->unique()->toArray();

        $categories = Category::withCount(['products','childes'])->with(['childes' => function($query)  {
            $query->withCount(['products','childes']);
        }])
        ->where(['position'=>0,'status'=>1])
        ->when(config('module.current_module_data'), function($query){
            $query->module(config('module.current_module_data')['id']);
        })
        ->whereIn('id',$item_categories)
        ->orderBy('priority','desc')->get();

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories'=>$categories
        ];
    }

    public static function get_related_products($zone_id,$product_id,$user_id=null)
    {
        $product = Item::find($product_id);
        $query = Item::active()
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where('category_ids', $product->category_ids)
        ->where('id', '!=', $product->id);

        $query = PersonalizationService::applyItemPersonalization($query, $user_id);

        return $query->limit(10)->get();
    }
    public static function get_related_store_products($zone_id,$product_id,$user_id=null)
    {
        $product = Item::find($product_id);
        $query = Item::active()
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where('store_id', $product->store_id)
        ->where('id', '!=', $product->id);

        $query = PersonalizationService::applyItemPersonalization($query, $user_id);

        return $query->limit(10)->get();
    }




    public static function recommended_items(
        $zone_id,
        $store_id = null,
        $limit = null,
        $offset = null,
        $type = 'all',
        $filter = 'all',
        $store_category_id = null,
        $user_id = null
    ) {
        $zones = !empty($zone_id) ? json_decode($zone_id, true) : null;

        $query = Item::query()
            ->when(isset($store_id), function ($q) use ($store_id) {
                $q->where('store_id', $store_id);
            })
            ->when(is_numeric($store_category_id), function ($q) use ($store_category_id) {
                $q->where('store_category_id', $store_category_id);
            })
            ->active(
                zone_ids: empty($store_id) ? $zones : null,
                module_id: empty($store_id) ? (config('module.current_module_data')['id'] ?? null) : null,
            )
            ->type($type)
            ->Recommended()

            ->when($filter === 'new_arrival', fn($q) => $q->latest())
            ->when($filter === 'top_rated', fn($q) => $q->withCount('reviews')->orderBy('reviews_count', 'desc'))
            ->when($filter === 'best_selling', fn($q) => $q->popular());

        if ($filter === 'all') {
            $query = PersonalizationService::applyItemPersonalization($query, $user_id);
        }

        if ($limit !== null && $offset !== null) {
            $paginator = $query->paginate($limit, ['*'], 'page', $offset);
            $items = $paginator->items();
            $total = $paginator->total();
        } else {
            $items = $query->limit(50)->get();
            $total = $items->count();
        }

        return [
            'total_size' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $items
        ];
    }


    public static function popular_products($zone_id, $limit = 25, $offset = 1, $type = 'all', $category_ids = null, $filter = null,$min=0, $max=false, $rating_count = null, $search = null, $store_category_id = null, $user_id = null)
    {
        $popular_item_default_status = Helpers::get_business_settings('popular_item_default_status') ?? 1;
        $popular_item_sort_by_general = Helpers::getPriorityList(name: 'popular_item_sort_by_general', type: 'general');
        $popular_item_sort_by_unavailable = Helpers::getPriorityList(name: 'popular_item_sort_by_unavailable', type: 'unavailable');
        $popular_item_sort_by_temp_closed = Helpers::getPriorityList(name: 'popular_item_sort_by_temp_closed', type: 'temp_closed');
        $category_ids = isset($category_ids)?(is_array($category_ids)?$category_ids:json_decode($category_ids)):[];

        $withCount = [];
        if ($filter && in_array('top_rated', $filter)) {
                $withCount[] = 'reviews';
            }
            if ($filter && in_array('most_loved', $filter)) {
                $withCount[] = 'whislists';
            }
            if ($popular_item_sort_by_general === 'review_count') {
                $withCount[] = 'reviews';
            }

            $zones = self::decodeValidZoneIds($zone_id);

            $query = Item::with('store')
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zones, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type);

            $query =self::filterQurey($query,$filter,$min??0,$max,$category_ids,$rating_count,$withCount,$search,$store_category_id);

            if ($popular_item_default_status == '1'){
                $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);
                $query = $query->popular();
            } else {

                if(config('module.current_module_data')['module_type']  !== 'food'){
                      $query = match ($popular_item_sort_by_unavailable) {
                        'remove' => $query->where('stock', '>', 0),
                        'last' => $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END'),
                        default => $query,
                    };
                }

                 $query = match ($popular_item_sort_by_temp_closed) {
                    'remove' => $query->having('temp_available', '>', 0),
                    'last' => $query->orderByDesc('temp_available'),
                     default => $query,
                };

                $query = match ($popular_item_sort_by_general) {
                    'rating' => $query->orderByDesc('avg_rating'),
                    'review_count' => $query->orderByDesc('reviews_count'),
                    'order_count' => $query->orderByDesc('order_count'),
                    'a_to_z' => $query->orderBy('name'),
                    'z_to_a' => $query->orderByDesc('name'),
                    'latest_created' =>$query->latest(),
                    'first_created' =>$query->oldest(),
                    default => $query,
                };
            }

            $paginator = $query->paginate($limit, ['*'], 'page', $offset);

            return [
                'total_size' => $paginator->total(),
                'limit' => $limit,
                'offset' => $offset,
                'products' => $paginator->items(),
                'categories' => self::getCategoryData($paginator->items()),
            ];


    }

    public static function most_reviewed_products($zone_id, $limit = 25, $offset = 1, $type = 'all',$category_ids = null, $filter = null,$min=0, $max=false, $rating_count = null, $search = null, $store_category_id = null, $user_id = null)
    {
        $category_ids = isset($category_ids)?(is_array($category_ids)?$category_ids:json_decode($category_ids)):[];
        $best_reviewed_item_default_status = Helpers::get_business_settings('best_reviewed_item_default_status') ?? 1;

        $best_reviewed_item_sort_by_general = Helpers::getPriorityList(name: 'best_reviewed_item_sort_by_general', type: 'general');
        $best_reviewed_item_sort_by_unavailable = Helpers::getPriorityList(name: 'best_reviewed_item_sort_by_unavailable', type: 'unavailable');
        $best_reviewed_item_sort_by_temp_closed = Helpers::getPriorityList(name: 'best_reviewed_item_sort_by_temp_closed', type: 'temp_closed');
        $withCount = [];
        if ($filter && in_array('most_loved', $filter)) {
            $withCount[] = 'whislists';
        }


        $zones = self::decodeValidZoneIds($zone_id);

        $query = Item::with('store')
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->withCount('reviews')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zones, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type)
             ->having('reviews_count' ,'>',0);

            $query =self::filterQurey($query,$filter,$min??0,$max,$category_ids,$rating_count,$withCount, $search, $store_category_id);

            if ($best_reviewed_item_default_status == '1'){
                $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);
                $query = $query->orderBy('reviews_count','desc');
            } else {
                if(config('module.current_module_data')['module_type']  !== 'food'){
                    $query = match ($best_reviewed_item_sort_by_unavailable) {
                        'remove' => $query->where('stock', '>', 0),
                        'last' => $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END'),
                        default => $query,
                    };
                }
               $query = match ($best_reviewed_item_sort_by_temp_closed) {
                    'remove' => $query->having('temp_available', '>', 0),
                    'last' => $query->orderByDesc('temp_available'),
                     default => $query,
                };
              $query = match ($best_reviewed_item_sort_by_general) {
                    'rating' => $query->orderByDesc('avg_rating'),
                    'review_count' => $query->orderByDesc('reviews_count'),
                    'order_count' => $query->orderByDesc('order_count'),
                    default => $query,
                };
            }

            $paginator = $query->paginate($limit, ['*'], 'page', $offset);
            return [
                'total_size' => $paginator->total(),
                'limit' => $limit,
                'offset' => $offset,
                'products' => $paginator->items(),
                'categories' => self::getCategoryData($paginator->items()),
            ];

    }

    public static function top_rated_products($zone_id, $limit = 25, $offset = 1, $type = 'all', $category_ids = null, $filter = null, $min = 0, $max = false, $rating_count = null, $search = null, $store_category_id = null, $user_id = null)
    {
        $category_ids = isset($category_ids) ? (is_array($category_ids) ? $category_ids : json_decode($category_ids)) : [];
        $best_reviewed_item_default_status = Helpers::get_business_settings('best_reviewed_item_default_status') ?? 1;
        $best_reviewed_item_sort_by_general = Helpers::getPriorityList(name: 'best_reviewed_item_sort_by_general', type: 'general');
        $best_reviewed_item_sort_by_unavailable = Helpers::getPriorityList(name: 'best_reviewed_item_sort_by_unavailable', type: 'unavailable');
        $best_reviewed_item_sort_by_temp_closed = Helpers::getPriorityList(name: 'best_reviewed_item_sort_by_temp_closed', type: 'temp_closed');

        $withCount = ['reviews'];
        if ($filter && in_array('most_loved', $filter)) {
            $withCount[] = 'whislists';
        }

        $zones = self::decodeValidZoneIds($zone_id);

        $query = Item::with('store')
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zones, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type)
            ->where('avg_rating', '>', 0);

        $query = self::filterQurey($query, $filter, $min ?? 0, $max, $category_ids, $rating_count, $withCount, $search, $store_category_id);

        if ($best_reviewed_item_default_status == '1'){
            $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);
            $query = $query->orderByDesc('avg_rating')->orderByDesc('rating_count')->orderByDesc('reviews_count');
        } else {
            if(config('module.current_module_data')['module_type']  !== 'food'){
                $query = match ($best_reviewed_item_sort_by_unavailable) {
                    'remove' => $query->where('stock', '>', 0),
                    'last' => $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END'),
                    default => $query,
                };
            }

            $query = match ($best_reviewed_item_sort_by_temp_closed) {
                'remove' => $query->having('temp_available', '>', 0),
                'last' => $query->orderByDesc('temp_available'),
                default => $query,
            };

            $query = match ($best_reviewed_item_sort_by_general) {
                'review_count' => $query->orderByDesc('reviews_count'),
                'order_count' => $query->orderByDesc('order_count'),
                'a_to_z' => $query->orderBy('name'),
                'z_to_a' => $query->orderByDesc('name'),
                'latest_created' => $query->latest(),
                'first_created' => $query->oldest(),
                'rating' => $query->orderByDesc('avg_rating')->orderByDesc('rating_count'),
                default => $query->orderByDesc('avg_rating')->orderByDesc('rating_count'),
            };
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);
        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories' => self::getCategoryData($paginator->items()),
        ];
    }

    public static function recently_viewed_products($zone_id, $limit = 25, $offset = 1, $type = 'all', $category_ids = null, $filter = null, $min = 0, $max = false, $rating_count = null, $search = null, $store_category_id = null, $user_id = null)
    {
        $category_ids = isset($category_ids) ? (is_array($category_ids) ? $category_ids : json_decode($category_ids)) : [];
        $withCount = [];

        if ($filter && in_array('top_rated', $filter)) {
            $withCount[] = 'reviews';
        }
        if ($filter && in_array('most_loved', $filter)) {
            $withCount[] = 'whislists';
        }

        $visitorLogQuery = DB::table('visitor_logs')
            ->selectRaw('visitor_log_id, SUM(visit_count) as total_view_count')
            ->where('visitor_log_type', Item::class)
            ->groupBy('visitor_log_id');

        $zones = self::decodeValidZoneIds($zone_id);

        $query = Item::with('store')
            ->joinSub($visitorLogQuery, 'visitor_log_summary', function ($join) {
                $join->on('visitor_log_summary.visitor_log_id', '=', 'items.id');
            })
            ->select(['items.*'])
            ->selectRaw('COALESCE(visitor_log_summary.total_view_count, 0) as total_view_count')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zones, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type);

        $query = self::filterQurey($query, $filter, $min ?? 0, $max, $category_ids, $rating_count, $withCount, $search, $store_category_id);
        $query = $query->orderByDesc('total_view_count')->latest('items.created_at');

        $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories' => self::getCategoryData($paginator->items()),
        ];
    }

    public static function discounted_products($zone_id, $limit = 25, $offset = 1, $type = 'all', $category_ids = null, $filter = null,$min=0, $max=false, $rating_count = null, $brand_ids = null, $search = null, $store_category_id = null, $user_id = null)
    {

        $special_offer_default_status = Helpers::get_business_settings('special_offer_default_status') ?? 1;
        $special_offer_sort_by_general = Helpers::getPriorityList(name: 'special_offer_sort_by_general', type: 'general');
        $special_offer_sort_by_unavailable = Helpers::getPriorityList(name: 'special_offer_sort_by_unavailable', type: 'unavailable');
        $withCount = [];
            if ($filter && in_array('top_rated', $filter)) {
                $withCount[] = 'reviews';
            }
            if ($filter && in_array('most_loved', $filter)) {
                $withCount[] = 'whislists';
            }
            if ($special_offer_sort_by_general === 'review_count') {
                $withCount[] = 'reviews';
            }

        $category_ids = isset($category_ids)?(is_array($category_ids)?$category_ids:json_decode($category_ids)):[];
        $brand_ids = isset($brand_ids)?(is_array($brand_ids)?$brand_ids:json_decode($brand_ids)):[];

            $zones = self::decodeValidZoneIds($zone_id);

            $query = Item::with('store')
            ->when(isset($brand_ids) && (count($brand_ids)>0), function($query)use($brand_ids){
                $query->whereHas('ecommerce_item_details',function($q)use($brand_ids){
                     $q->whereHas('brand',function($q)use($brand_ids){
                        return $q->whereIn('id',$brand_ids);
                    });
                });
            })
            ->whereHas('store', function($query)use($filter){
                $query->when($filter&&in_array('free_delivery',$filter),function ($qurey){
                    return $qurey->where('free_delivery',1);
                })
                ->when($filter&&in_array('coupon',$filter),function ($qurey){
                    return $qurey->has('activeCoupons');
                });
            })
            ->Discounted()
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zones, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type);
            $query =self::filterQurey($query,$filter,$min??0,$max,$category_ids,$rating_count,$withCount,$search,$store_category_id);


            if($special_offer_default_status == '1') {
                $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);
                $query = $query->orderBy('discount','desc');
            }else{
                if(config('module.current_module_data')['module_type']  !== 'food'){
                    $query = match ($special_offer_sort_by_unavailable) {
                        'remove' =>$query->where('stock', '>', 0),
                        'last' => $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END'),
                        default => $query,
                    };
                }

              $query =  match ($special_offer_sort_by_general) {
                    'rating' => $query->orderByDesc('avg_rating'),
                    'review_count' => $query->orderByDesc('reviews_count'),
                    'order_count' => $query->orderByDesc('order_count'),
                    'a_to_z' => $query->orderBy('name'),
                    'z_to_a' => $query->orderByDesc('name'),
                    default => $query,
                };
            }

            $paginator = $query->paginate($limit, ['*'], 'page', $offset);
        return [
            'total_size' =>  $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories' => self::getCategoryData($paginator->items()),
        ];
    }


    private static function decodeValidZoneIds($zone_id)
    {
        $zones = is_array($zone_id) ? $zone_id : json_decode((string) $zone_id, true);
        if (!is_array($zones) && is_numeric($zones)) {
            $zones = [(int) $zones];
        }

        return is_array($zones) && !empty($zones) ? $zones : null;
    }

    private static function filterQurey($query,$filter,$min,$max,$category_ids,$rating_count,$withCount,$search,$store_category_id = null){
        $key = $search ? explode(' ', $search ?? ''):[];

        $query =  $query->withCount(array_unique($withCount));

           $query = $query->when(isset($category_ids) && (count($category_ids)>0), function($query)use($category_ids){
                $query->whereHas('category',function($q)use($category_ids){
                    $q->where(function ($q) use ($category_ids) {
                            $q->whereIn('id', $category_ids)->orWhereIn('parent_id', $category_ids);
                        });
                    });
            })
            ->when(is_numeric($store_category_id), function($query)use($store_category_id){
                $query->where('store_category_id', $store_category_id);
            })
            ->when($search, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    }
                });
            })
            ->when($max, function($query)use($min,$max){
                $query->whereBetween('price',[$min,$max]);
            })

            ->when($rating_count, function($query) use ($rating_count){
                $query->where('avg_rating', '>=' , $rating_count);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                $qurey->orderByDesc('reviews_count');
            })
            ->when($filter && in_array('most_loved',$filter),function ($qurey){
                $qurey->having('whislists_count' ,'>',0);
            })
            ->when($filter && in_array('popular',$filter),function ($qurey){
                  $qurey->popular();
            })

            // ->when($filter && in_array('available_now', $filter), function ($query) {
            //         $query->where(function ($q) {
            //             $currentTime = now()->format('H:i:s');
            //             $q->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
            //             ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
            //         });
            //     })

            ->when($filter && in_array('available_now', $filter) && !in_array('un_available_now', $filter), function ($query) {
                $query->Available(now()->format('H:i:s'));
            })
            ->when($filter && in_array('un_available_now', $filter)&& !in_array('available_now', $filter), function ($query) {
                $query->UnAvailable(now()->format('H:i:s'));
            })
            ->when($filter && in_array('latest',$filter),function ($qurey){
                $qurey->whereBetween('created_at', [now()->subYear(), now()]);            })
           ->when($filter && in_array('high',$filter),function ($qurey){
                $qurey->orderByDesc('price');
            })
            ->when($filter && in_array('low',$filter),function ($qurey){
                $qurey->orderBy('price');
            })
            ->when($filter && in_array('z_to_a',$filter),function ($qurey){
                $qurey->orderByDesc('name');
            })
            ->when($filter && in_array('a_to_z',$filter),function ($qurey){
                $qurey->orderBy('name');
            });

            return $query;
    }

    private static function getCategoryData($products){
        $productCollection = collect(is_array($products) ? $products : [$products]);
        $item_categories = $productCollection->pluck('category_ids')->filter()->toArray();
            $item_categories = array_reduce($item_categories, function($carry, $jsonString) {
                $decoded = is_string($jsonString) ? json_decode($jsonString, true) : $jsonString;
                if (!is_array($decoded)) return $carry;
                $filtered = array_filter($decoded, fn($item) => isset($item['position']) && $item['position'] == 1);
                $carry = array_merge($carry, array_column($filtered, 'id'));
                return $carry;
            }, []);

            $item_categories = array_unique($item_categories);
            $categories = Category::
            whereIn('id',$item_categories)
            ->orderBy('priority','desc')->select('id','name','image')->get()
            ->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'image_full_url' => $category->image_full_url
                ];
            });
            return $categories;
    }

    public static function brand_products($zone_id, $limit = null, $offset = null, $type = 'all', $category_ids = null, $filter = null,$min=false, $max=false, $rating_count = null, $brand_ids = null, $store_category_id = null)
    {
        $category_ids = isset($category_ids)?(is_array($category_ids)?$category_ids:json_decode($category_ids)):[];
        $brand_ids = isset($brand_ids)?(is_array($brand_ids)?$brand_ids:json_decode($brand_ids)):[];
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';

            $paginator = Item::
            whereHas('module.zones', function($query)use($zone_id){
                return $query->whereIn('zones.id', json_decode($zone_id, true));
            })
                ->when(isset($category_ids) && (count($category_ids)>0), function($query)use($category_ids){
                    return $query->whereHas('category',function($q)use($category_ids){
                        return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
                    });
                })
                ->when(is_numeric($store_category_id), function($query)use($store_category_id){
                    return $query->where('store_category_id', $store_category_id);
                })
                ->when(isset($brand_ids) && (count($brand_ids)>0), function($query)use($brand_ids){
                    return  $query->whereHas('ecommerce_item_details',function($q)use($brand_ids){
                        return $q->whereHas('brand',function($q)use($brand_ids){
                            return $q->whereIn('id',$brand_ids);
                        });
                    });
                })
                ->whereHas('store', function($query)use($zone_id ,$filter){
                    return $query->when(config('module.current_module_data'), function($query){
                        return $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                            return $query->where('modules.id', config('module.current_module_data')['id']);
                        });
                    })->whereIn('zone_id', json_decode($zone_id, true))
                    ->when($filter&&in_array('free_delivery',$filter),function ($qurey){
                        return $qurey->where('free_delivery',1);
                    })

                    ->when($filter&&in_array('coupon',$filter),function ($qurey){
                        return $qurey->has('activeCoupons');
                    });
                })->active()->type($type)
                ->when($rating_count, function($query) use ($rating_count){
                    return $query->where('avg_rating', '>=' , $rating_count);
                })
                ->when($min && $max, function($query)use($min,$max){
                    return $query->whereBetween('price',[$min,$max]);
                })
                ->when($filter && in_array('top_rated',$filter),function ($qurey){
                    return $qurey->withCount('reviews')->orderBy('reviews_count','desc');
                })
                ->when($filter && in_array('popular',$filter),function ($qurey){
                    return $qurey->popular();
                })
                ->when($filter && in_array('high',$filter),function ($qurey){
                    return $qurey->orderBy('price', 'desc');
                })
                ->when($filter && in_array('low',$filter),function ($qurey){
                    return $qurey->orderBy('price', 'asc');
                })
                ->when($filter && in_array('available_now', $filter), function ($query) {
                        $query->where(function ($q) {
                            $currentTime = now()->format('H:i:s');
                            $q->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
                            ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
                        });
                    })
                ->when($filter && in_array('discounted',$filter),function ($qurey){
                    return $qurey->Discounted()->orderBy('discount','desc');
                });

            if($limit != null && $offset != null)
            {
                $paginator = $paginator->paginate($limit, ['*'], 'page', $offset);
            } else{
                $paginator = $paginator->limit(50)->get();
            }

            $paginatedItems = is_array($paginator) ? $paginator : (method_exists($paginator, 'items') ? $paginator->items() : $paginator->all());
            $item_categories = collect($paginatedItems)->pluck('category_id')->unique()->toArray();

            $categories = Category::withCount(['products','childes'])->with(['childes' => function($query)  {
                $query->withCount(['products','childes']);
            }])
                ->where(['position'=>0,'status'=>1])
                ->when(config('module.current_module_data'), function($query){
                    $query->module(config('module.current_module_data')['id']);
                })
                ->whereIn('id',$item_categories)
                ->orderBy('priority','desc')->get();

            return [
                'total_size' => $limit != null && $offset != null ? $paginator->total() : $paginator->count(),
                'limit' => $limit,
                'offset' => $offset,
                'products' => $limit != null && $offset != null ? $paginator->items() : $paginator,
                'categories' => $categories,
            ];
    }
    public static function get_product_review($id)
    {
        $reviews = Review::where('product_id', $id)->get();
        return $reviews;
    }

    public static function get_rating($reviews)
    {
        $rating5 = 0;
        $rating4 = 0;
        $rating3 = 0;
        $rating2 = 0;
        $rating1 = 0;
        foreach ($reviews as $key => $review) {
            if ($review->rating == 5) {
                $rating5 += 1;
            }
            if ($review->rating == 4) {
                $rating4 += 1;
            }
            if ($review->rating == 3) {
                $rating3 += 1;
            }
            if ($review->rating == 2) {
                $rating2 += 1;
            }
            if ($review->rating == 1) {
                $rating1 += 1;
            }
        }
        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }

    public static function get_avg_rating($rating)
    {
        $total_rating = 0;
        $total_rating += $rating[1];
        $total_rating += $rating[2]*2;
        $total_rating += $rating[3]*3;
        $total_rating += $rating[4]*4;
        $total_rating += $rating[5]*5;

        return $total_rating/array_sum($rating);
    }

    public static function get_overall_rating($reviews)
    {
        $totalRating = count($reviews);
        $rating = 0;
        foreach ($reviews as $key => $review) {
            $rating += $review->rating;
        }
        if ($totalRating == 0) {
            $overallRating = 0;
        } else {
            $overallRating = number_format($rating / $totalRating, 2);
        }

        return [$overallRating, $totalRating];
    }

    public static function format_export_items($foods,$module_type)
    {
        $storage = [];
        foreach($foods as $item)
        {
            $category_id = 0;
            $sub_category_id = 0;
            foreach(json_decode($item->category_ids, true) as $key=>$category)
            {
                if($key==0)
                {
                    $category_id = $category['id'];
                }
                else if($key==1)
                {
                    $sub_category_id = $category['id'];
                }
            }
            $addOns = json_decode($item->add_ons, true);
            $variations = json_decode($item->variations, true);
            $foodVariations = json_decode($item->food_variations, true);
            $choiceOptions = json_decode($item->choice_options, true);
            $attributes = json_decode($item->attributes, true);

            $storage[] = [
                'Id'=>$item->id,
                'Name'=>$item->name,
                'Description'=>$item->description,
                'Image'=>$item->image,
                'Images'=>$item->images,
                'CategoryId'=>$category_id,
                'SubCategoryId'=>$sub_category_id,
                'UnitId'=>$item->unit_id,
                'Stock'=>$item->stock,
                'Price'=>$item->price,
                'Discount'=>$item->discount,
                'DiscountType'=>$item->discount_type,
                'AvailableTimeStarts'=>$item->available_time_starts,
                'AvailableTimeEnds'=>$item->available_time_ends,
                'Variations'=>$module_type == 'food'?(!empty($foodVariations) ? $item->food_variations : null):(!empty($variations) ? $item->variations : null),
                'ChoiceOptions'=>!empty($choiceOptions) ? $item->choice_options : null,
                'AddOns'=>!empty($addOns) ? $item->add_ons : null,
                'Attributes'=>!empty($attributes) ? $item->attributes : null,
                'StoreId'=>$item->store_id,
                'ModuleId'=>$item->module_id,
                'Status'=>$item->status == 1 ? 'active' : 'inactive',
                'Veg'=>$item->veg == 1 ? 'yes' : 'no',
                'Recommended'=>$item->recommended == 1 ? 'yes' : 'no',
            ];
            if ($module_type === 'pharmacy') {
                $storage[count($storage) - 1]['IsPrescriptionRequired']
                    = $item?->pharmacy_item_details?->is_prescription_required ?? 0;
                $storage[count($storage) - 1]['CommonConditions']
                    = $item?->pharmacy_item_details?->common_condition_id ?? 0;
                $storage[count($storage) - 1]['IsBasic']
                    = $item?->pharmacy_item_details?->is_basic ?? 0;
                $storage[count($storage) - 1]['UnitValue']
                    = $item?->pharmacy_item_details?->unit_value;
                $storage[count($storage) - 1]['Manufacturer']
                    = $item?->pharmacy_item_details?->manufacturer;
            }
            if (in_array($module_type, ['ecommerce', 'grocery'], true)) {
                $storage[count($storage) - 1]['BrandId']
                    = $item?->ecommerce_item_details?->brand_id ?? 0;
            }
        }

        return $storage;
    }

    public static function format_export_vehicles($vehicles, $module_type): array
    {
        $storage = [];
        foreach($vehicles as $vehicle)
        {
            $storage[] = [
                'Id' => $vehicle->id,
                'Name' => $vehicle->name,
                'Description' => $vehicle->description ?? null,
                'Thumbnail' => $vehicle->thumbnail ?? null,
                'Images' => $vehicle->images ?? null,
                'ZoneId' => $vehicle->zone_id ?? null,
                'ProviderId' => $vehicle->provider_id ?? null,
                'BrandId' => $vehicle->brand_id ?? null,
                'CategoryId' => $vehicle->category_id ?? null,
                'Model' => $vehicle->model ?? null,
                'Type' => $vehicle->type ?? null,
                'EngineCapacity' => $vehicle->engine_capacity ?? null,
                'EnginePower' => $vehicle->engine_power ?? null,
                'SeatingCapacity' => $vehicle->seating_capacity ?? null,
                'AirCondition' => $vehicle->air_condition ?? 0,
                'FuelType' => $vehicle->fuel_type ?? null,
                'TransmissionType' => $vehicle->transmission_type ?? null,
                'MultipleVehicles' => $vehicle->multiple_vehicles ?? 0,
                'TripHourly' => $vehicle->trip_hourly ?? 0,
                'TripDistance' => $vehicle->trip_distance ?? 0,
                'TripDayWise' => $vehicle->trip_day_wise ?? 0,
                'HourlyPrice' => $vehicle->hourly_price ?? 0.00,
                'DayWisePrice' => $vehicle->day_wise_price ?? 0.00,
                'DistancePrice' => $vehicle->distance_price ?? 0.00,
                'DiscountType' => $vehicle->discount_type ?? null,
                'DiscountPrice' => $vehicle->discount_price ?? 0.00,
                'Tag' => $vehicle->tag ?? null,
                'Documents' => $vehicle->documents ?? null,
                'Status' => $vehicle->status ?? 1,
                'NewTag' => $vehicle->new_tag ?? 1,
            ];
        }

        return $storage;
    }

    public static function update_food_ratings()
    {
        try{
            $foods = Item::withOutGlobalScopes()->whereHas('reviews')->with('reviews')->get();
            foreach($foods as $key=>$food)
            {
                $foods[$key]->avg_rating = $food->reviews->avg('rating');
                $foods[$key]->rating_count = $food->reviews->count();
                foreach($food->reviews as $review)
                {
                    $foods[$key]->rating = self::update_rating($foods[$key]->rating, $review->rating);
                }
                $foods[$key]->save();
            }
        }catch(\Exception $e){
            info($e->getMessage());
            return false;
        }
        return true;
    }

    public static function update_rating($ratings, $product_rating)
    {

        $store_ratings = [1=>0 , 2=>0, 3=>0, 4=>0, 5=>0];
        if(isset($ratings))
        {
            $store_ratings = json_decode($ratings, true);
            $store_ratings[$product_rating] = $store_ratings[$product_rating] + 1;
        }
        else
        {
            $store_ratings[$product_rating] = 1;
        }
        return json_encode($store_ratings);
    }

    public static function update_stock($item, $quantity, $variant=null)
    {
        if(isset($variant))
        {
            $variations = is_array($item['variations'])?$item['variations']: json_decode($item['variations'], true);

            foreach ($variations as $key => $value) {
                if ($value['type'] == $variant) {
                    $variations[$key]['stock'] -= $quantity;
                }
            }
            $item['variations']= json_encode($variations);
        }
        $item->stock -= $quantity;
        return $item;
    }

    public static function update_flash_stock($item, $quantity, $decreaseStock =false)
    {
        $item = FlashSaleItem::Active()->whereHas('flashSale', function ($query) {
            $query->Active()->Running();
        })
        ->where(['item_id' => $item->id])->first();
        if($item){

            if ($decreaseStock) {
                $item->sold = max(0, $item->sold - $quantity);
            } else {
                $item->sold += $quantity;
            }
            $item->available_stock = max(0, $item->stock - $item->sold);
        }
        return $item;
    }

    public static function cart_suggest_products($zone_id, $store_id, $limit = null, $offset = null, $type = 'all', $recomended = false, $user_id = null)
    {
        $zoneIds = self::decodeValidZoneIds($zone_id);

        $query = Item::where('store_id', $store_id)
            ->active(
                zone_ids: $zoneIds,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zoneIds, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type)
            ->whereHas('store', function ($q) {
                $q->Weekday();
            })
            ->when($recomended, fn($q) => $q->Recommended())
            ->withCount('reviews')
            ->orderBy('reviews_count', 'desc');

        $query = PersonalizationService::applyItemPersonalization($query, $user_id);

        if ($limit !== null && $offset !== null) {
            $paginator = $query->paginate($limit, ['*'], 'page', $offset);
            return [
                'total_size' => $paginator->total(),
                'limit'      => $limit,
                'offset'     => $offset,
                'items'      => $paginator->items(),
            ];
        }

        $items = $query->limit(50)->get();
        return [
            'total_size' => $items->count(),
            'limit'      => $limit,
            'offset'     => $offset,
            'items'      => $items,
        ];
    }

    public static function get_popular_basic_products($zone_id, $limit, $offset, $type, $store_id =null, $category_id=null, $min=false, $max=false,$product_id=null, $user_id=null)
    {
        $basic_medicine_default_status = Helpers::get_business_settings('basic_medicine_default_status') ?? 1;
        $basic_medicine_sort_by_general = Helpers::getPriorityList(name: 'basic_medicine_sort_by_general', type: 'general');
        $basic_medicine_sort_by_unavailable = Helpers::getPriorityList(name: 'basic_medicine_sort_by_unavailable', type: 'unavailable');
        $basic_medicine_sort_by_temp_closed = Helpers::getPriorityList(name: 'basic_medicine_sort_by_temp_closed', type: 'temp_closed');

        if(isset($category_id)&&($category_id != 0)){
            $category_id = explode(',', $category_id);
        }
        $query = Item::active()->type($type)
        ->whereHas('pharmacy_item_details', function($query){
            $query->where('is_basic', 1);
        })
        ->when(isset($category_id)&&($category_id != 0), function($q)use($category_id){
            $q->whereHas('category',function($q)use($category_id){
                return $q->whereIn('id',$category_id)->orWhereIn('parent_id', $category_id);
            });
        })
        ->when(isset($product_id), function($q)use($product_id){
            $q->where('id', '!=', $product_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->when(isset($store_id)&&is_numeric($store_id),function ($qurey) use($store_id){
            $qurey->where('store_id', $store_id);
        })
        ->when(isset($store_id)&&(!is_numeric($store_id)), function ($query) use ($store_id) {
            $query->whereHas('store', function ($q) use ($store_id) {
                return $q->where('slug', $store_id);
            });
        })
        ->select(['items.*'])
        ->selectSub(function ($subQuery) {
            $subQuery->selectRaw('active as temp_available')
                ->from('stores')
                ->whereColumn('stores.id', 'items.store_id');
        }, 'temp_available')
        ->active()->type($type);

        if ($basic_medicine_default_status == '1'){
            $query = $query->popular();
        } else {
            if(config('module.current_module_data')['module_type']  !== 'food'){
                if($basic_medicine_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($basic_medicine_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if($basic_medicine_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($basic_medicine_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }

            if ($basic_medicine_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($basic_medicine_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($basic_medicine_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($basic_medicine_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($basic_medicine_sort_by_general == 'order_count') {
                $query = $query->orderByDesc('order_count');
            }

        }

        $query = PersonalizationService::applyItemPersonalization($query, $user_id);

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        $categories = self::getCategoryData($paginator->items());

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories'=>$categories
        ];
    }

    public static function organic_products($zone_id, $limit = 25, $offset = 1, $type = 'all', $category_ids = null, $filter = null, $min = false, $max = false, $rating_count = null, $search = null, $user_id = null)
    {
        $latest_items_default_status = 1;
        $latest_items_sort_by_general = Helpers::getPriorityList(name: 'latest_items_sort_by_general', type: 'general');
        $latest_items_sort_by_unavailable = Helpers::getPriorityList(name: 'latest_items_sort_by_unavailable', type: 'unavailable');
        $latest_items_sort_by_temp_closed = Helpers::getPriorityList(name: 'latest_items_sort_by_temp_closed', type: 'temp_closed');

        $category_ids = isset($category_ids) ? (is_array($category_ids) ? $category_ids : json_decode($category_ids)) : [];
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, "[]"), ',')) : '';

        $withCount = [];
        if ($filter && in_array('top_rated', $filter)) {
            $withCount[] = 'reviews';
        }
        if ($filter && in_array('most_loved', $filter)) {
            $withCount[] = 'whislists';
        }
        if ($latest_items_sort_by_general === 'review_count') {
            $withCount[] = 'reviews';
        }

        $zones = self::decodeValidZoneIds($zone_id);

        $query = Item::with('store')
            ->where('organic', 1)
            ->whereHas('store', function($query)use($filter){
                $query->when($filter && in_array('free_delivery',$filter),function ($qurey){
                        return $qurey->where('free_delivery',1);
                    })
                    ->when($filter && in_array('coupon',$filter),function ($qurey){
                        return $qurey->has('activeCoupons');
                    });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active(
                zone_ids: $zones,
                module_id: config('module.current_module_data')['id'] ?? null,
            )
            ->when(!$zones, fn($q) => $q->whereRaw('0 = 1'))
            ->type($type);

        $query = self::filterQurey($query, $filter, $min ?? 0, $max, $category_ids, $rating_count, $withCount, $search);

        if ($latest_items_default_status == '1'){
            $query = $query->latest();
        } else {
            if(config('module.current_module_data')['module_type']  !== 'food'){
                if($latest_items_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($latest_items_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if($latest_items_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($latest_items_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }

            if ($latest_items_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($latest_items_sort_by_general == 'review_count') {
                $query = $query->orderByDesc('reviews_count');
            } elseif ($latest_items_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($latest_items_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($latest_items_sort_by_general == 'latest_created') {
                $query = $query->latest();
            } elseif ($latest_items_sort_by_general == 'first_created') {
                $query = $query->oldest();
            }
        }

        $query = PersonalizationService::applyItemPersonalization($query, $user_id, $filter);

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories' => self::getCategoryData($paginator->items()),
        ];
    }

    public static function recent_ordered_items($user_id, $zone_id, $limit = 10, $offset = 1, $type = 'all', $module_id = null)
    {
        $zones = json_decode($zone_id, true);

        $query = Item::with('store')
            ->when($module_id, function ($query) use ($module_id) {
                $query->where('module_id', $module_id);
            })
            ->when(!$module_id && config('module.current_module_data'), function ($query) {
                $query->where('module_id', config('module.current_module_data')['id']);
            })
            ->whereHas('module.zones', function($query)use($zones){
                $query->whereIn('zones.id', $zones);
            })
            ->whereHas('store', function($query)use($zones, $module_id){
                $query->whereIn('zone_id', $zones)
                    ->when($module_id, function ($query) use ($module_id) {
                        $query->where('module_id', $module_id)->whereHas('zone.modules', function($q) use ($module_id){
                            $q->where('modules.id', $module_id);
                        });
                    })
                    ->when(!$module_id && config('module.current_module_data'), function ($query) {
                        $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function($q){
                            $q->where('modules.id', config('module.current_module_data')['id']);
                        });
                    });
            })
            ->whereHas('orders.order', function($query)use($user_id, $module_id){
                $query->where('user_id', $user_id)
                    ->where('is_guest', 0)
                    ->where('order_type', '<>', 'pos')
                    ->whereNotIn('order_status', ['failed', 'canceled'])
                    ->when($module_id, function ($query) use ($module_id) {
                        $query->where('module_id', $module_id);
                    });
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) use ($user_id, $module_id) {
                $subQuery->from('order_details')
                    ->join('orders', 'orders.id', '=', 'order_details.order_id')
                    ->selectRaw('MAX(orders.created_at)')
                    ->whereColumn('order_details.item_id', 'items.id')
                    ->where('orders.user_id', $user_id)
                    ->where('orders.is_guest', 0)
                    ->where('orders.order_type', '<>', 'pos')
                    ->whereNotIn('orders.order_status', ['failed', 'canceled'])
                    ->when($module_id, function ($query) use ($module_id) {
                        $query->where('orders.module_id', $module_id);
                    });
            }, 'latest_ordered_at')
            ->active()
            ->type($type)
            ->orderByDesc('latest_ordered_at');

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'items' => $paginator->items(),
        ];
    }

    public static function get_offer_items(?int $moduleId, ?string $zoneHeader, ?int $userId, int $limit = 20, int $offset = 1, ?\Illuminate\Http\Request $request = null): array
    {
        $zones = self::decodeZones($zoneHeader);
        $limit = max(1, $limit);
        $offset = max(1, $offset);
        $module = config('module.current_module_data');

        $type = $request?->query('type', 'all') ?? 'all';
        $search = $request?->query('search');
        $search = $search !== null && trim((string) $search) !== '' ? trim((string) $search) : null;

        $category_ids = $request?->query('category_ids');
        $category_ids = $category_ids ? (is_array($category_ids) ? $category_ids : json_decode($category_ids, true)) : [];
        $category_ids = is_array($category_ids) ? array_values(array_filter(array_map('intval', $category_ids))) : [];

        $brand_ids = $request?->query('brand_ids');
        $brand_ids = $brand_ids ? (is_array($brand_ids) ? $brand_ids : json_decode($brand_ids, true)) : [];
        $brand_ids = is_array($brand_ids) ? array_values(array_filter(array_map('intval', $brand_ids))) : [];

        $filters = self::resolveSearchFilters($request, $request?->query('filter'));
        $filter_by = $filters['filter_by'];

        $additional_data = [
            'sort_by' => $filters['sort_by'],
            'filter_by' => $filters['filter_by'],
            'store_category_id' => $request?->query('store_category_id'),
        ];

        $query = Item::with(['store', 'storeCategory', 'module'])
            ->active()
            ->type($type)

            ->when($module, fn ($qq) => $qq->where('module_id', $module['id']))
            ->when(! $module && is_numeric($moduleId), fn ($qq) => $qq->where('module_id', $moduleId))
            ->when(! empty($category_ids), function ($qq) use ($category_ids) {
                $qq->whereHas('category', function ($q) use ($category_ids) {
                    $q->whereIn('id', $category_ids)->orWhereIn('parent_id', $category_ids);
                });
            })
            ->when(! empty($brand_ids), function ($qq) use ($brand_ids) {
                $qq->whereHas('ecommerce_item_details', function ($q) use ($brand_ids) {
                    $q->whereHas('brand', fn ($b) => $b->whereIn('id', $brand_ids));
                });
            })
            ->when($search, fn ($qq) => $qq->search(keywords: $search, relations: [
                'translations' => 'value',
                'tags' => 'tag',
                'category.parent' => 'name',
                'category' => 'name',
                'ecommerce_item_details.brand' => 'name',
            ]))
            ->Discounted()
            ->select('items.*')
            ->orderByDesc('discount')
            ->applyRating($request)
            ->applyFilters($additional_data)
            ->applySorting($additional_data['sort_by'])
            ->applyPriceRange($request);

        $query = PersonalizationService::applyItemPersonalization($query, $userId, $filter_by);

        $paginator = $query->paginate($limit, ['items.*'], 'page', $offset);

        $items = collect($paginator->items());
        $formatted = Helpers::productListDataFormatting($items);
        $source = $items->values();

        foreach ($formatted as $i => &$row) {
            $item = $source[$i] ?? null;
            if (! $item) {
                continue;
            }
            $d = Helpers::product_discount_calculate($item, $item->price, $item->store, true);
            $row['discounted_price'] = max(0, round((float) $item->price - (float) ($d['discount_amount'] ?? 0), 2));
            $row['discount_type'] = $d['original_discount_type'] ?? $item->discount_type;
            $row['store_image'] = $item->store?->logo_full_url;
            $row['wishlist'] = 0;
        }
        unset($row);

        $payload = [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'items' => $formatted,
        ];

        self::applyWishlist($payload, $userId);

        return $payload;
    }

    private static function applyWishlist(array &$payload, ?int $userId): void
    {
        if (empty($payload['items'])) {
            return;
        }

        if (! $userId) {
            foreach ($payload['items'] as &$row) {
                $row['wishlist'] = 0;
            }
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(fn ($r) => (int) ($r['id'] ?? 0), $payload['items']))));
        $set = $ids
            ? array_flip(DB::table('wishlists')->where('user_id', $userId)->whereIn('item_id', $ids)->pluck('item_id')->all())
            : [];

        foreach ($payload['items'] as &$row) {
            $row['wishlist'] = isset($set[$row['id'] ?? 0]) ? 1 : 0;
        }
    }

    public static function get_offer_stores(?int $moduleId, ?string $zoneHeader, float $longitude = 0.0, float $latitude = 0.0, int $page = 1, int $perPage = 10, ?string $q = null, ?array $filter = null): array
    {
        $zones = self::decodeZones($zoneHeader);
        $term = $q !== null ? trim($q) : '';
        $filter = $filter ?? [];
        if ($term !== '' && empty($filter['search'])) {
            $filter['search'] = $term;
        }

        $query = Store::WithOpenWithDeliveryTime($longitude, $latitude)
            ->Active()
            ->withCount('reviews')
            ->withItemRatingAvg('avg_r')
            ->when(is_numeric($moduleId), fn ($qq) => $qq->where('module_id', $moduleId))
            ->when(! empty($zones), fn ($qq) => $qq->whereIn('zone_id', $zones))
            ->whereHas('items', function ($qq) {
                $qq->active();
            })
            ->with(['items' => function ($qq) {
                $qq->active()
                    ->orderByDesc('discount')->orderByDesc('id');
            }])
            ->whereHas('discount', function ($q) {
                $q->validate();
            });

        if ($filter) {
            $query = $query->applyStoreFilter($filter);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $stores = collect($paginator->items())
            ->map(fn ($s) => self::mapStore($s))
            ->values()
            ->all();

        return [
            'total_size' => $paginator->total(),
            'limit' => $perPage,
            'offset' => $page,
            'stores' => $stores,
        ];
    }

    private static function mapStore($store): array
    {
        $offerItems = $store->relationLoaded('items')
            ? collect($store->getRelation('items'))->take(5)->values()
            : collect();

        $top_items = $offerItems->map(fn ($item) => self::mapItem($item, $store))->values()->all();

        return \App\CentralLogics\StoreLogic::format_store_for_listing($store, [
            'top_items' => $top_items,
            'with_items' => true,
        ]);
    }

    private static function mapItem($item, $store): array
    {
        $d = Helpers::product_discount_calculate($item, $item->price, $store, true);
        $price = (float) $item->price;
        $discounted = max(0, round($price - (float) ($d['discount_amount'] ?? 0), 2));

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'image_full_url' => $item->image_full_url,
            'price' => $price,
            'discounted_price' => $discounted,
            'discount' => (float) ($d['discount_percentage'] ?? 0),
            'discount_type' => $d['original_discount_type'] ?? $item->discount_type,
        ];
    }

    private static function decodeZones(?string $zoneId): array
    {
        if (empty($zoneId)) {
            return [];
        }
        $z = json_decode($zoneId, true);
        if (! is_array($z)) {
            return [];
        }
        $out = array_values(array_filter($z, 'is_numeric'));
        sort($out);
        return $out;
    }
}
