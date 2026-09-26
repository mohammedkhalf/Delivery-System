<?php

namespace App\CentralLogics;

use Exception;
use App\Models\Item;
use App\Models\Store;
use App\Models\Category;
use App\Models\DataSetting;
use App\Models\Advertisement;

use App\Models\StoreSchedule;
use App\Models\FlashSale;
use App\Models\ItemCampaign;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;

class StoreLogic
{
    public static function get_stores( $zone_id, $filter_data, $type, $store_type, $limit = 10, $offset = 1, $featured=false,$longitude=0,$latitude=0,$filter=null,$rating_count=null, ?array $store_filter = null, $user_id = null, $module_id = null)
    {

        $all_stores_default_status = Helpers::get_business_settings('all_stores_default_status') ?? 1;
        $all_stores_sort_by_general = Helpers::getPriorityList(name: 'all_stores_sort_by_general', type: 'general');
        $all_stores_sort_by_unavailable = Helpers::getPriorityList(name: 'all_stores_sort_by_unavailable', type: 'unavailable');
        $all_stores_sort_by_temp_closed = Helpers::getPriorityList(name: 'all_stores_sort_by_temp_closed', type: 'temp_closed');

        $query = Store::type($type)->
        WithOpenWithDeliveryTime($longitude??0,$latitude??0)
            ->withCount(['items','campaigns','reviews','orders'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->whereHas('module',function($query){
                return  $query->active();
            })
            ->Active();
        if(config('module.current_module_data')) {
            $query = $query->whereHas('zone.modules', function($query){
                return  $query->where('modules.id', config('module.current_module_data')['id']);
            })->module(config('module.current_module_data')['id'])
                ->when(!config('module.current_module_data')['all_zone_service'], function($query)use($zone_id){
                    return  $query->whereIn('zone_id', json_decode($zone_id,true));
                });
        } else {
            $query = $query->whereIn('zone_id', json_decode($zone_id,true));
            $query = $query->when(is_numeric($module_id), fn ($q) => $q->where('module_id', $module_id));
        }

            if($all_stores_default_status != '1') {
                if($all_stores_sort_by_temp_closed == 'remove'){
                    $query = $query->where('active', '>', 0);
                }elseif($all_stores_sort_by_temp_closed == 'last'){
                    $query = $query->orderByDesc('active');
                }

                if($all_stores_sort_by_unavailable == 'remove'){
                    $query = $query->having('open', '>', 0);
                }elseif($all_stores_sort_by_unavailable == 'last'){
                    $query = $query->orderBy('open', 'desc');
                }

                if($all_stores_sort_by_general == 'rating') {
                    $query = $query->selectSub(function ($query) {
                        $query->selectRaw('AVG(reviews.rating)')
                            ->from('reviews')
                            ->join('items', 'items.id', '=', 'reviews.item_id')
                            ->whereColumn('items.store_id', 'stores.id')
                            ->groupBy('items.store_id');
                    }, 'avg_r')->orderBy('avg_r', 'desc');
                }elseif($all_stores_sort_by_general == 'review_count') {
                    $query = $query->orderByDesc('reviews_count');
                }elseif($all_stores_sort_by_general == 'order_count') {
                    $query = $query->orderBy('orders_count', 'desc');
                }elseif($all_stores_sort_by_general == 'latest_created') {
                    $query = $query->latest();
                }elseif($all_stores_sort_by_general == 'first_created') {
                    $query = $query->oldest();
                }elseif($all_stores_sort_by_general == 'a_to_z') {
                    $query = $query->orderBy('name');
                }elseif($all_stores_sort_by_general == 'z_to_a') {
                    $query = $query->orderByDesc('name');
                }
            }
            $query = $query->when($filter && in_array('free_delivery',$filter),function ($qurey){
                return $qurey->where('free_delivery',1);
            });
            $query = $query->when($filter && in_array('coupon', $filter), function ($query) {
                return $query->has('activeCoupons');
            });
            $query = $query->when($store_type == 'all' && $filter && !in_array('fast_delivery',$filter), function($q){
                return $q->orderBy('open', 'desc')->orderBy('distance');
            });
            $query = $query->when($filter && (in_array('currently_open', $filter) || in_array('open_now', $filter)), function ($query) {
                return $query->having('open', '>', 0);
            });
            $query = $query->when($filter && in_array('rx_accepted', $filter), function ($query) {
                return $query->whereHas('items.pharmacy_item_details', function ($q) {
                    $q->where('is_prescription_required', 1);
                });
            });
            $query = $query->when($store_type == 'newly_joined', function($q){
                return $q->latest();
            });
            $query = $query->when($rating_count, function($query) use ($rating_count){
                return  $query->selectSub(function ($query) use ($rating_count){
                    return $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            });
            $query = $query->when(($filter && in_array('top_rated',$filter) ) || $store_type == 'top_rated' ,function ($qurey){
                return $qurey->quickActionFilter('top_rated');
            });
            $query = $query->when(($filter && in_array('popular',$filter)) || $store_type == 'popular'  ,function ($qurey){
                return  $qurey->withCount('orders')->orderBy('orders_count', 'desc');
            });
            $query = $query->when($filter && in_array('discounted',$filter),function ($qurey){
                return $qurey->where(function ($query) {
                    return $query->whereHas('items', function ($q) {
                        $q->Discounted();
                    });
                });
            });
            $query = $query->when($filter && in_array('open',$filter),function ($qurey){
                return $qurey->orderBy('open', 'desc');
            });
            $query = $query->when(($filter && in_array('nearby',$filter)) || $store_type == 'nearby'  ,function ($qurey){
                return  $qurey->quickActionFilter('nearby');
            });
            $query = $query->when($filter_data=='delivery', function($q){
                return $q->delivery();
            });

            $query = $query->when($filter_data=='take_away', function($q){
                return $q->takeaway();
            });
            $query = $query->when($featured, function($query){
                return $query->featured();
            });
            $query = $query->when($filter && in_array('fast_delivery',$filter) , function($q) {
                return $q->orderBy('open', 'desc')->orderBy('min_delivery_time');
            });

            if($all_stores_default_status == '1') {
                $query = $query->withExists('advertisements')->orderByDesc('advertisements_exists');

                $query = PersonalizationService::applyStorePersonalization($query, $user_id, $filter);
                $query = $query->orderBy('open', 'desc');
            }

            if ($store_filter) {
                $query = $query->applyStoreFilter($store_filter);
            }


        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        $store_ids = collect($paginator->items())->pluck('id')->all();
        $top_items_by_store = ! empty($store_ids)
            ? Store::topItemsByIds($store_ids, 5)
            : collect();

        self::attachStoreCategoryIds($paginator);

        $paginator->each(function ($store) use ($top_items_by_store) {
            $items = $top_items_by_store[(int) $store->id] ?? collect();
            $store->top_items = $items->map(function ($item) use ($store) {
                $discountData = Helpers::product_discount_calculate($item, $item->price, $store, true);
                $price = (float) $item->price;
                $discounted = max(0, round($price - (float) ($discountData['discount_amount'] ?? 0), 2));

                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'image_full_url' => $item->image_full_url,
                    'price' => $price,
                    'discounted_price' => $discounted,
                    'discount' => (float) ($discountData['discount_percentage'] ?? $item->discount),
                    'discount_type' => $discountData['original_discount_type'] ?? $item->discount_type,
                    'order_count' => (int) $item->order_count,
                    'avg_rating' => (float) ($item->avg_rating ?? 0),
                ];
            })->values()->all();

            $store->discount_status = !empty($store->items->where('discount', '>', 0));
            unset($store['items']);
        });
        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $paginator->items()
        ];
    }

    public static function get_latest_stores($zone_id, $limit = 50, $offset = 1, $type='all',$longitude=0,$latitude=0,$user_id=null)
    {
    $latest_stores_default_status = Helpers::get_business_settings('latest_stores_default_status') ?? 1;
    $latest_stores_sort_by_general = Helpers::getPriorityList(name: 'latest_stores_sort_by_general', type: 'general');
    $latest_stores_sort_by_unavailable = Helpers::getPriorityList(name: 'latest_stores_sort_by_unavailable', type: 'unavailable');
    $latest_stores_sort_by_temp_closed = Helpers::getPriorityList(name: 'latest_stores_sort_by_temp_closed', type: 'temp_closed');




    $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns','reviews'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->Active()
            ->type($type);

            if($latest_stores_default_status == '1'){
                $query = PersonalizationService::applyStorePersonalization($query, $user_id);
                $query = $query->latest();
            } else{

                if($latest_stores_default_status != '1') {
                    if($latest_stores_sort_by_unavailable == 'remove'){
                        $query = $query->where('active', '>', 0);
                    }elseif($latest_stores_sort_by_unavailable == 'last'){
                        $query = $query->orderByDesc('active');
                    }

                    if($latest_stores_sort_by_temp_closed == 'remove'){
                        $query = $query->having('open', '>', 0);
                    }elseif($latest_stores_sort_by_temp_closed == 'last'){
                        $query = $query->orderBy('open', 'desc');
                    }

                    if($latest_stores_sort_by_general == 'rating') {
                        $query = $query->selectSub(function ($query) {
                            $query->selectRaw('AVG(reviews.rating)')
                                ->from('reviews')
                                ->join('items', 'items.id', '=', 'reviews.item_id')
                                ->whereColumn('items.store_id', 'stores.id')
                                ->groupBy('items.store_id');
                        }, 'avg_r')->orderBy('avg_r', 'desc');
                    }elseif($latest_stores_sort_by_general == 'review_count') {
                        $query = $query->orderByDesc('reviews_count');
                    }elseif($latest_stores_sort_by_general == 'order_count') {
                        $query = $query->orderBy('orders_count', 'desc');
                    }elseif($latest_stores_sort_by_general == 'latest_created') {
                        $query = $query->latest();
                    }elseif($latest_stores_sort_by_general == 'first_created') {
                        $query = $query->oldest();
                    }elseif($latest_stores_sort_by_general == 'a_to_z') {
                        $query = $query->orderBy('name');
                    }elseif($latest_stores_sort_by_general == 'z_to_a') {
                        $query = $query->orderByDesc('name');
                    }
                }
            }


            $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_verified_stores($zone_id, $limit = 50, $offset = 1, $type = 'all', $longitude = 0, $latitude = 0)
    {
        $query = Store::withOpen($longitude ?? 0, $latitude ?? 0)
            ->withCount(['items', 'campaigns', 'reviews'])
            ->with(['discount' => function ($q) {
                return $q->validate();
            }])
            ->whereHas('storeConfig', function ($q) {
                $q->where('verified_seller', 1);
            })
            ->when(config('module.current_module_data'), function ($query) use ($zone_id) {
                $query->whereHas('zone.modules', function ($query) {
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if (!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->Active()
            ->type($type)
            ->orderBy('name');

        $paginator = $query->paginate($limit ?? 50, ['*'], 'page', $offset ?? 1);

        self::attachStoreCategoryIds($paginator);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit ?? 50,
            'offset' => $offset ?? 1,
            'stores' => $paginator->items()
        ];
    }

    private static function attachStoreCategoryIds($paginator): void
    {
        $store_ids = collect($paginator->items())->pluck('id')->all();
        if (empty($store_ids)) {
            return;
        }

        $categoriesByStore = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('order_details', 'order_details.item_id', '=', 'items.id')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->selectRaw('
                items.store_id as store_id,
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id,
                categories.name,
                COUNT(order_details.id) as order_count
            ')
            ->whereIn('items.store_id', $store_ids)
            ->where('categories.status', 1)
            ->whereNotIn('orders.order_status', ['failed', 'canceled'])
            ->groupBy('items.store_id', 'id', 'categories.parent_id', 'categories.name')
            ->orderByDesc('order_count')
            ->get()
            ->groupBy('store_id');

        $paginator->each(function ($store) use ($categoriesByStore) {
            $mergedIds = [];
            $mergedCategoryNames = [];

            foreach (($categoriesByStore[$store->id] ?? collect())->take(5) as $item) {
                if ($item->id != 0) {
                    $mergedIds[] = $item->id;
                    $mergedCategoryNames[] = $item->name;
                }
                if ($item->parent_id != 0) {
                    $mergedIds[] = $item->parent_id;
                    $mergedCategoryNames[] = $item->name;
                }
            }

            $store->category_ids = array_values(array_unique($mergedIds));
            $store->category_names = array_values(array_unique($mergedCategoryNames));
        });
    }

    public static function get_distance_wise_stores($zone_id, $limit = 50, $offset = 1, $type='all',$longitude=0,$latitude=0,$name=null,$user_id=null)
    {
        $key = $name ? explode(' ', $name) : [];
        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns','reviews'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when($name, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                    $relationships = [
                        'translations' => 'value',
                    ];
                    return $q->applyRelationShipSearch(relationships:$relationships ,searchParameter:$key);
                });
            })
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->Active()
            ->type($type)
            ->orderBy('distance');

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_popular_stores($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$user_id=null)
    {
        $popular_store_default_status = Helpers::get_business_settings('popular_store_default_status') ?? 1;
        $popular_store_sort_by_general = Helpers::getPriorityList(name: 'popular_store_sort_by_general', type: 'general');
        $popular_store_sort_by_unavailable = Helpers::getPriorityList(name: 'popular_store_sort_by_unavailable', type: 'unavailable');
        $popular_store_sort_by_temp_closed = Helpers::getPriorityList(name: 'popular_store_sort_by_temp_closed', type: 'temp_closed');
        $popular_store_sort_by_rating = Helpers::getPriorityList(name: 'popular_store_sort_by_rating', type: 'rating');

        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->type($type)
            ->withCount('reviews')
            ->withCount('orders')->Active();

            if($popular_store_default_status == '1') {
                $query = PersonalizationService::applyStorePersonalization($query, $user_id);
                $query = $query->orderBy('open', 'desc')
                        ->orderBy('distance')
                        ->orderBy('orders_count', 'desc');
            }else{
                if($popular_store_sort_by_temp_closed == 'remove'){
                    $query = $query->where('active', '>', 0);
                }elseif($popular_store_sort_by_temp_closed == 'last'){
                    $query = $query->orderByDesc('active');
                }

                if($popular_store_sort_by_unavailable == 'remove'){
                    $query = $query->having('open', '>', 0);
                }elseif($popular_store_sort_by_unavailable == 'last'){
                    $query = $query->orderBy('open', 'desc');
                }

                $rating_threshold = 0;
                if($popular_store_sort_by_rating && ($popular_store_sort_by_rating != 'none')){
                    $rating_threshold = match($popular_store_sort_by_rating) {
                        'four_plus' => 4,
                        'three_half_plus' => 3.5,
                        'three_plus' => 3,
                        'two_plus' => 2,
                        default => 0
                    };
                }

                if($rating_threshold > 0 || $popular_store_sort_by_general == 'rating') {
                    $query = $query->selectSub(function ($query) use ($rating_threshold) {
                        $query->selectRaw('AVG(reviews.rating)')
                            ->from('reviews')
                            ->join('items', 'items.id', '=', 'reviews.item_id')
                            ->whereColumn('items.store_id', 'stores.id')
                            ->groupBy('items.store_id')
                            ->when($rating_threshold > 0, function($q) use ($rating_threshold) {
                                return $q->havingRaw('AVG(reviews.rating) >= ?', [$rating_threshold]);
                            });
                    }, 'store_rating');

                    if($rating_threshold > 0) {
                        $query->having('store_rating', '>=', $rating_threshold);
                    }

                    if($popular_store_sort_by_general == 'rating') {
                        $query->orderBy('store_rating', 'desc');
                    }
                } elseif($popular_store_sort_by_general == 'review_count') {
                    $query = $query->orderByDesc('reviews_count');
                } elseif($popular_store_sort_by_general == 'order_count') {
                    $query = $query->orderBy('orders_count', 'desc');
                } elseif($popular_store_sort_by_general == 'nearest_first') {
                    $query = $query->orderBy('distance');
                }
            }

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_discounted_stores($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$filter=null,$rating_count=null, ?array $store_filter = null, $user_id = null)
    {
        $query = Store::WithOpenWithDeliveryTime($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                return   $query->whereHas('zone.modules', function($query){
                    return $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    return  $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->where(function ($query) {
                return  $query->whereHas('items', function ($q) {
                    $q->Discounted();
                });
            })
            ->Active()
            ->type($type)
            ->when($filter && in_array('free_delivery',$filter),function ($qurey){
                return $qurey->where('free_delivery',1);
            })
            ->when($filter && in_array('coupon',$filter),function ($qurey){
                return $qurey->has('activeCoupons');
            })
            ->when($rating_count, function($query) use ($rating_count){
                return  $query->selectSub(function ($query) use ($rating_count){
                    return  $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                return $qurey->whereNotNull('rating')->whereRaw("LENGTH(rating) > 0");
            })
            ->when($filter && in_array('currently_open',$filter),function ($qurey){
                return $qurey->having('open', '>', 0);
            })
            ->orderBy('open', 'desc')
            ->when($filter && in_array('popular',$filter),function ($qurey){
                return $qurey->withCount('orders')->orderBy('orders_count', 'desc');
            })
            ->when(($filter && in_array('nearby',$filter))   ,function ($qurey){
                return  $qurey->orderBy('distance');
            })
            ->when($filter && in_array('fast_delivery',$filter),function ($qurey){
                return $qurey->orderBy('min_delivery_time');
            })
            ->when($store_filter, fn ($q) => $q->applyStoreFilter($store_filter));

        $query = PersonalizationService::applyStorePersonalization($query, $user_id);

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        $paginator->each(function ($store) {
            $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id
            ')
                ->where('items.store_id', $store->id)
                ->where('categories.status', 1)
                ->groupBy('id', 'categories.parent_id')
                ->get();

            $data = json_decode($category_ids, true);

            $mergedIds = [];

            foreach ($data as $item) {
                if ($item['id'] != 0) {
                    $mergedIds[] = $item['id'];
                }
                if ($item['parent_id'] != 0) {
                    $mergedIds[] = $item['parent_id'];
                }
            }

            $category_ids = array_values(array_unique($mergedIds));

            $store->category_ids = $category_ids;

            $store->discount_status = !empty($store->items->where('discount', '>', 0));
            unset($store['items']);
        });

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_top_rated_stores($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$user_id=null)
    {
        $query = Store::withOpen($longitude??0,$latitude??0)->whereNotNull('rating')
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->Active()
            ->type($type)
            ->whereRaw("LENGTH(rating) > 0");

        $query = PersonalizationService::applyStorePersonalization($query, $user_id);

        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_store_details($store_id,$longitude=0,$latitude=0)
    {
        return Store::withOpen($longitude??0,$latitude??0)->with(['discount'=>function($q){
            return $q->validate();
        }, 'campaigns', 'schedules','activeCoupons','store_sub'])
            ->withCount(['items','campaigns','reviews_comments'])
            ->when(config('module.current_module_data'), function($query){
                $query->module(config('module.current_module_data')['id']);
            })
            ->when(is_numeric($store_id),function ($qurey) use($store_id){
                $qurey->where('id', $store_id);
            })
            ->when(!is_numeric($store_id),function ($qurey) use($store_id){
                $qurey->where('slug', $store_id);
            })
            ->first();
    }

    public static function calculate_store_rating($ratings)
    {
        $total_submit = $ratings[0]+$ratings[1]+$ratings[2]+$ratings[3]+$ratings[4];
        $positive_submit = $ratings[0]+$ratings[1]+$ratings[2];
        $rating = ($ratings[0]*5+$ratings[1]*4+$ratings[2]*3+$ratings[3]*2+$ratings[4])/($total_submit?$total_submit:1);
        $positive_rating = $total_submit>0?(($positive_submit*100)/$total_submit):0;
        return ['rating'=>round($rating,2), 'total'=>$total_submit, 'positive_rating'=>$positive_rating];
    }

    public static function update_store_rating($ratings, $product_rating)
    {
        $store_ratings = [1=>0 , 2=>0, 3=>0, 4=>0, 5=>0];
        if($ratings)
        {
            $store_ratings[1] = $ratings[4];
            $store_ratings[2] = $ratings[3];
            $store_ratings[3] = $ratings[2];
            $store_ratings[4] = $ratings[1];
            $store_ratings[5] = $ratings[0];
            $store_ratings[$product_rating] = $ratings[5-$product_rating] + 1;
        }
        else
        {
            $store_ratings[$product_rating] = 1;
        }
        return json_encode($store_ratings);
    }

    public static function search_stores($name, $zone_id, $category_id= null,$limit = 10, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$filter=null,$rating_count=null,$category_ids=null, ?array $store_filter = null, $user_id = null, $request = null, $additional_data = [])
    {
        $key = explode(' ', $name);
        if (empty($additional_data['filter_by']) && is_array($filter) && !empty($filter)) {
            $additional_data['filter_by'] = $filter;
        }
        $paginator = Store::WithOpenWithDeliveryTime($longitude??0,$latitude??0)
        ->whereHas('zone.modules', function($query){
            return $query->where('modules.id', config('module.current_module_data')['id']);
        })
        ->withCount(['items','campaigns'])->with(['discount'=>function($q){
            return $q->validate();
        }])->weekday()

            ->when(config('module.current_module_data'), function($query)use($zone_id){
                return   $query->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    return   $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->when($category_id, function($query)use($category_id){
                return $query->whereHas('items.category', function($q)use($category_id){
                    return $q->whereId($category_id)->orWhere('parent_id', $category_id);
                });
            })
            ->when($category_ids && is_array($category_ids), function($query)use($category_ids){
                return $query->whereHas('items.category', function($q)use($category_ids){
                    return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
                });
            })
            ->active()
            ->when($rating_count, function($query) use ($rating_count){
                return $query->selectSub(function ($query) use ($rating_count){
                    return  $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->orderBy('open', 'desc')
            ->type($type)
            ->applyFilters($additional_data)
            ->applySorting($additional_data['sort_by'] ?? 'default')
            ->applyRating($request)
            ->applyPriceRange($request)
            ->search(keywords: $key, relations: [
                'translations' => 'value',
                'items' => 'name',
                'items.translations' => 'value',
                'items.tags' => 'tag',
                'items.category' => 'name',
                'items.category.parent' => 'name',
                'items.nutritions' => 'nutrition',
                'items.allergies' => 'allergy',
                'items.generic' => 'generic_name',
                'items.ecommerce_item_details.brand' => 'name',
                'items.pharmacy_item_details.common_condition' => 'name',
            ]);

            $paginator = PersonalizationService::applyStorePersonalization($paginator, $user_id, $filter);
            $paginator = $paginator->paginate($limit, ['*'], 'page', $offset);


        $store_ids = $paginator->getCollection()->pluck('id')->all();

        $top_items_by_store = !empty($store_ids)
            ? Store::topItemsByIds($store_ids, 5)
            : collect();

        $discount_store_ids = empty($store_ids) ? [] : DB::table('items')
            ->whereIn('store_id', $store_ids)
            ->where('discount', '>', 0)
            ->distinct()
            ->pluck('store_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $paginator->each(function ($store) use ($top_items_by_store, $discount_store_ids) {
            $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id
            ')
                ->where('items.store_id', $store->id)
                ->where('categories.status', 1)
                ->groupBy('id', 'categories.parent_id')
                ->get();

            $data = json_decode($category_ids, true);

            $mergedIds = [];

            foreach ($data as $item) {
                if ($item['id'] != 0) {
                    $mergedIds[] = $item['id'];
                }
                if ($item['parent_id'] != 0) {
                    $mergedIds[] = $item['parent_id'];
                }
            }

            $category_ids = array_values(array_unique($mergedIds));

            $store->category_ids = $category_ids;
            $store->discount_status = in_array((int) $store->id, $discount_store_ids, true);
            $store->top_items = ($top_items_by_store[$store->id] ?? collect())->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'image_full_url' => $item->image_full_url,
                    'price' => (float) $item->price,
                    'discount' => (float) $item->discount,
                    'discount_type' => $item->discount_type,
                    'order_count' => (int) $item->order_count,
                    'avg_rating' => (float) ($item->avg_rating ?? 0),
                ];
            })->values()->all();
            unset($store['items']);
        });

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'stores' => $paginator->items()
        ];
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

    public static function get_earning_data($vendor_id)
    {
        $monthly_earning = OrderTransaction::whereMonth('created_at', date('m'))->NotRefunded()->where('vendor_id', $vendor_id)->sum('store_amount');
        $weekly_earning = OrderTransaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->NotRefunded()->where('vendor_id', $vendor_id)->sum('store_amount');
        $daily_earning = OrderTransaction::whereDate('created_at', now())->NotRefunded()->where('vendor_id', $vendor_id)->sum('store_amount');

        return['monthely_earning'=>(float)$monthly_earning, 'weekly_earning'=>(float)$weekly_earning, 'daily_earning'=>(float)$daily_earning];
    }

    public static function format_export_stores($stores)
    {
        $storage = [];
        foreach($stores as $item)
        {
            if($item->stores->count()<1)
            {
                break;
            }
            $storage[] = [
                'Id'=>$item->stores[0]->id,
                'OwnerId'=>$item->id,
                'OwnerFirstName'=>$item->f_name,
                'OwnerLastName'=>$item->l_name,
                'ProviderName'=>$item->stores[0]->name,
                'Phone'=>$item->phone,
                'Email'=>$item->email,
                'Logo'=>$item->stores[0]->logo,
                'CoverPhoto'=>$item->stores[0]->cover_photo,
                'Latitude'=>$item->stores[0]->latitude,
                'Longitude'=>$item->stores[0]->longitude,
                'Address'=>$item->stores[0]->address ?? null,
                'ZoneId'=>$item->stores[0]->zone_id,
                'ModuleId'=>$item->stores[0]->module_id,
                'Comission'=>$item->stores[0]->comission ?? 0,
                'Tax'=>$item->stores[0]->tax ?? 0,
                'PickupTime'=>$item->stores[0]->delivery_time ?? '20-30',
                'ScheduleTrip'=> $item->stores[0]->schedule_order == 1 ? 'yes' : 'no',
                'Status'=> $item->stores[0]->status == 1 ? 'active' : 'inactive',
                'ReviewsSection'=> $item->stores[0]->reviews_section == 1 ? 'active' : 'inactive',
                'storeOpen'=> $item->stores[0]->active == 1 ? 'yes' : 'no',
            ];
        }

        return $storage;
    }

    public static function insert_schedule(int $store_id, array $days=[0,1,2,3,4,5,6], String $opening_time='00:00:00', String $closing_time='23:59:59')
    {
        $data = array_map(function($item)use($store_id, $opening_time, $closing_time){
            return     ['store_id'=>$store_id,'day'=>$item,'opening_time'=>$opening_time,'closing_time'=>$closing_time];
        },$days);
        try{
            StoreSchedule::upsert($data,['store_id','day','opening_time','closing_time']);
            return true;
        }catch(Exception $e)
        {
            return $e;
        }
        return false;

    }

    public static function format_store_sales_export_data($items)
    {
        $data = [];
        foreach($items as $key=>$item)
        {

            $data[]=[
                '#'=>$key+1,
                translate('messages.name')=>$item->name,
                translate('messages.quantity')=>$item->orders->sum('quantity'),
                translate('messages.gross_sale')=>$item->orders->sum('price'),
                translate('messages.discount_given')=>$item->orders->sum('discount_on_item'),

            ];
        }
        return $data;
    }

    public static function format_store_summary_export_data($stores)
    {
        $data = [];
        foreach($stores as $key=>$store)
        {
            $delivered = $store->orders->where('order_status', 'delivered')->count();
            $canceled = $store->orders->where('order_status', 'canceled')->count();
            $refunded = $store->orders->where('order_status', 'refunded')->count();
            $total = $store->orders->count();
            $refund_requested = $store->orders->whereNotNull('refund_requested')->count();
            $data[]=[
                '#'=>$key+1,
                translate('Store')=>$store->name,
                translate('Total Order')=>$total,
                translate('Delivered Order')=>$delivered,
                translate('Total Amount')=>$store->orders->where('order_status','delivered')->sum('order_amount'),
                translate('Completion Rate')=>($store->orders->count() > 0 && $delivered > 0)? number_format((100*$delivered)/$store->orders->count(), config('round_up_to_digit')): 0,
                translate('Ongoing Rate')=>($store->orders->count() > 0 && $delivered > 0)? number_format((100*($store->orders->count()-($delivered+$canceled)))/$store->orders->count(), config('round_up_to_digit')): 0,
                translate('Cancelation Rate')=>($store->orders->count() > 0 && $canceled > 0)? number_format((100*$canceled)/$store->orders->count(), config('round_up_to_digit')): 0,
                translate('Refund Request')=>$refunded,

            ];
        }
        return $data;
    }

    public static function get_recommended_stores($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0,$user_id=null)
    {
        $recommended_store_default_status = Helpers::get_business_settings('recommended_store_default_status') ?? 1;

        $recommended_store_sort_by_general = Helpers::getPriorityList(name: 'recommended_store_sort_by_general', type: 'general');
        $recommended_store_sort_by_unavailable = Helpers::getPriorityList(name: 'recommended_store_sort_by_unavailable', type: 'unavailable');
        $recommended_store_sort_by_temp_closed = Helpers::getPriorityList(name: 'recommended_store_sort_by_temp_closed', type: 'temp_closed');
        $recommended_store_sort_by_rating = Helpers::getPriorityList(name: 'recommended_store_sort_by_rating', type: 'rating');

        $shuffle=null;
        if(config('module.current_module_data')){
            $shuffle= DataSetting::where(['key' => 'shuffle_recommended_store' , 'type' => config('module.current_module_data')['id']])?->first()?->value;
        }
        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->wherehas('storeConfig', function ($q){
                $q->where(['is_recommended_deleted'=> 0 , 'is_recommended' => 1]);
            })
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->type($type)
            ->when($shuffle == 1 && $recommended_store_default_status == 1, function($q){
                $q->inRandomOrder();
            })
            ->withCount('reviews')
            ->withCount('orders')->Active();

        if($recommended_store_default_status == '1') {
            $query = PersonalizationService::applyStorePersonalization($query, $user_id);
        }else{

            if($recommended_store_sort_by_temp_closed == 'remove'){
                $query = $query->where('active', '>', 0);
            }elseif($recommended_store_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('active');
            }

            if($recommended_store_sort_by_unavailable == 'remove'){
                $query = $query->having('open', '>', 0);
            }elseif($recommended_store_sort_by_unavailable == 'last'){
                $query = $query->orderBy('open', 'desc');
            }

            if($recommended_store_sort_by_rating && ($recommended_store_sort_by_rating != 'none')){
                $rating_count = 0;
                if($recommended_store_sort_by_rating == 'four_plus'){
                    $rating_count = 4;
                }
                if($recommended_store_sort_by_rating == 'three_half_plus'){
                    $rating_count = 3.5;
                }
                if($recommended_store_sort_by_rating == 'three_plus'){
                    $rating_count = 3;
                }
                if($recommended_store_sort_by_rating == 'two_plus'){
                    $rating_count = 2;
                }

                $query = $query->selectSub(function ($query) use ($rating_count){
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            }

            if($recommended_store_sort_by_general == 'rating') {
                $query = $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id');
                }, 'avg_rat')->orderBy('avg_rat', 'desc');
            }elseif($recommended_store_sort_by_general == 'review_count') {
                $query = $query->orderByDesc('reviews_count');
            }elseif($recommended_store_sort_by_general == 'order_count') {
                $query = $query->orderBy('orders_count', 'desc');
            }

        }
        $paginator = $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_top_offer_near_me($zone_id, $limit = 50, $offset = 1, $type = 'all',$longitude=0,$latitude=0 , $name = null ,$sort = null, $halal = null, $user_id = null)
    {

        $top_offer_near_me_stores_default_status = Helpers::get_business_settings('top_offer_near_me_stores_default_status') ?? 1;
        $top_offer_near_me_stores_sort_by_general = Helpers::getPriorityList(name: 'top_offer_near_me_stores_sort_by_general', type: 'general');
        $top_offer_near_me_stores_sort_by_unavailable = Helpers::getPriorityList(name: 'top_offer_near_me_stores_sort_by_unavailable', type: 'unavailable');
        $top_offer_near_me_stores_sort_by_temp_closed = Helpers::getPriorityList(name: 'top_offer_near_me_stores_sort_by_temp_closed', type: 'temp_closed');



        $query = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns','reviews'])
            ->with('discount')
            ->whereHas('discount' , function($q){
                $q->validate();
            })
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->type($type)->Active()->Halal($halal);
            if($name){
                $key = explode(' ', $name);
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                    $relationships = [
                        'translations' => 'value',
                        // 'items.nutritions' => 'nutrition',
                        // 'items.allergies' => 'allergy',
                        // 'items.generic' => 'generic_name',
                        // 'items.ecommerce_item_details.brand' => 'name',
                        // 'items.pharmacy_item_details.common_condition' => 'name'
                    ];
                    return  $q->applyRelationShipSearch(relationships:$relationships ,searchParameter:$key);
                }) ->orderByRaw("CASE WHEN name = ? THEN 1 WHEN name LIKE ? THEN 2 ELSE 3 END, LENGTH(name) ASC, name ASC ", [$name, "%{$name}%"]);
            }

            if($sort)
            {
                $query->orderBy('name',$sort);

            } else{
                if($top_offer_near_me_stores_default_status== 1){
                    $query = PersonalizationService::applyStorePersonalization($query, $user_id);
                    $query= $query->orderByDesc('open')->orderby('distance');
                }else{

                    if($top_offer_near_me_stores_sort_by_temp_closed == 'remove'){
                        $query = $query->where('active', '>', 0);
                    }elseif($top_offer_near_me_stores_sort_by_temp_closed == 'last'){
                        $query = $query->orderByDesc('active');
                    }

                    if($top_offer_near_me_stores_sort_by_unavailable == 'remove'){
                        $query = $query->having('open', '>', 0);
                    }elseif($top_offer_near_me_stores_sort_by_unavailable == 'last'){
                        $query = $query->orderBy('open', 'desc');
                    }

                    if($top_offer_near_me_stores_sort_by_general == 'rating') {
                        $query = $query->selectSub(function ($query) {
                            $query->selectRaw('AVG(reviews.rating)')
                                ->from('reviews')
                                ->join('items', 'items.id', '=', 'reviews.item_id')
                                ->whereColumn('items.store_id', 'stores.id')
                                ->groupBy('items.store_id');
                        }, 'avg_rat')->orderBy('avg_rat', 'desc');
                    }elseif($top_offer_near_me_stores_sort_by_general == 'review_count') {
                        $query = $query->orderByDesc('reviews_count');
                    }elseif($top_offer_near_me_stores_sort_by_general == 'asc_discount') {


                        $query = $query->selectSub(function ($query) {
                            $query->selectRaw('MAX(discounts.discount)')
                                ->from('discounts')
                                ->whereColumn('discounts.store_id', 'stores.id');
                        }, 'discount')
                        ->orderBy('discount', 'asc');

                    }elseif($top_offer_near_me_stores_sort_by_general == 'desc_discount') {
                        $query = $query->selectSub(function ($query) {
                            $query->selectRaw('MAX(discounts.discount)')
                                ->from('discounts')
                                ->whereColumn('discounts.store_id', 'stores.id');
                        }, 'discount')
                        ->orderBy('discount', 'desc');
                    }
                }
            }

        $paginator= $query->paginate($limit??50, ['*'], 'page', $offset??1);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit??50,
            'offset' => $offset??1,
            'stores' => $paginator->items()
        ];
    }

    public static function get_quick_delivery_stores($zone_id, $limit = 10, $offset = 1, $type = 'all', $longitude = 0, $latitude = 0, $isAd = false, ?array $filter = null, $withItems = false, $user_id = null)
    {
        $advertised_store_ids = Advertisement::valid()
            ->when(config('module.current_module_data'), function ($query) {
                $query->where('module_id', config('module.current_module_data')['id']);
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->Active();
                if (config('module.current_module_data') && !config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                } elseif (!config('module.current_module_data')) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->pluck('store_id')
            ->unique()
            ->values()
            ->all();

        $query = Store::WithOpenWithDeliveryTime($longitude ?? 0, $latitude ?? 0)
            ->withCount(['items', 'campaigns', 'reviews'])
            ->with(['discount' => function ($q) {
                return $q->validate();
            }])
            ->selectSub(function ($q) {
                $q->selectRaw('AVG(reviews.rating)')
                    ->from('reviews')
                    ->join('items', 'items.id', '=', 'reviews.item_id')
                    ->whereColumn('items.store_id', 'stores.id')
                    ->groupBy('items.store_id');
            }, 'avg_r')
            ->when(config('module.current_module_data'), function ($query) use ($zone_id) {
                $query->whereHas('zone.modules', function ($query) {
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if (!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            }, function ($query) use ($zone_id) {
                $query->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->Active()
            ->type($type)
            ->has('items');

        if ($filter) {
            $query = $query->applyStoreFilter($filter);
        }

        if ($isAd && !empty($advertised_store_ids)) {
            $placeholders = implode(',', array_fill(0, count($advertised_store_ids), '?'));
            $query->orderByRaw("CASE WHEN stores.id IN ($placeholders) THEN 0 ELSE 1 END", $advertised_store_ids);
        }

        $query = $query->orderBy('open', 'desc');

        $query = PersonalizationService::applyStorePersonalization($query, $user_id);

        $stores = $query->orderBy('min_delivery_time', 'asc')
            ->orderBy('distance', 'asc')
            ->limit($limit ?? 10)
            ->offset((($offset ?? 1) - 1) * ($limit ?? 10))
            ->get();

        $new_store_days = (int) (Helpers::get_business_settings('new_store_tag_days') ?? 30);
        $new_threshold = now()->subDays($new_store_days);

        $top_items_by_store = $withItems && $stores->isNotEmpty()
            ? Store::topItemsByIds($stores->pluck('id')->all(), 5)
            : collect();

        $categories_by_store = $stores->isNotEmpty()
            ? self::topCategoriesByStoreIds($stores->pluck('id')->all(), 5)
            : collect();

        $formatted = $stores->map(function ($store) use ($advertised_store_ids, $new_threshold, $withItems, $top_items_by_store, $categories_by_store) {
            $top_items = null;
            if ($withItems) {
                $top_items = ($top_items_by_store[$store->id] ?? collect())->map(function ($item) {
                    return [
                        'id' => (int) $item->id,
                        'name' => $item->name,
                        'image_full_url' => $item->image_full_url,
                        'price' => (float) $item->price,
                        'discount' => (float) $item->discount,
                        'discount_type' => $item->discount_type,
                        'order_count' => (int) $item->order_count,
                        'avg_rating' => (float) ($item->avg_rating ?? 0),
                    ];
                })->values()->all();
            }

            return self::format_store_for_listing($store, [
                'advertised_store_ids' => $advertised_store_ids,
                'new_threshold' => $new_threshold,
                'top_items' => $top_items,
                'with_items' => $withItems,
                'category_data' => $categories_by_store[$store->id] ?? [],
            ]);
        })->values()->all();

        return [
            'total_size' => count($formatted),
            'limit' => $limit ?? 10,
            'offset' => $offset ?? 1,
            'stores' => $formatted,
        ];
    }

    public static function get_exclusive_deals($zone_id, $moduleId = null, $longitude = 0, $latitude = 0, int $limit = 25, $offset = 1, ?array $filter = null, $user_id = null)
    {
        $zones = $zone_id ? (json_decode($zone_id, true) ?: []) : [];
        $today = date('Y-m-d');
        $now = date('H:i:s');

        $advertised_store_ids = Advertisement::valid()
            ->when(is_numeric($moduleId), fn ($q) => $q->where('module_id', $moduleId))
            ->whereHas('store', function ($q) use ($zones) {
                $q->Active();
                if (! empty($zones)) {
                    $q->whereIn('zone_id', $zones);
                }
            })
            ->pluck('store_id')->unique()->values()->all();

        $query = Store::WithOpenWithDeliveryTime($longitude ?? 0, $latitude ?? 0)
            ->Active()
            ->when(is_numeric($moduleId), fn ($q) => $q->where('module_id', $moduleId))
            ->when(! empty($zones), fn ($q) => $q->whereIn('zone_id', $zones))
            ->whereHas('discount', fn ($q) => $q->validate())
            ->with(['discount' => fn ($q) => $q->validate(), 'schedules'])
            ->withCount('reviews')
            ->selectSub(function ($q) {
                $q->selectRaw('AVG(reviews.rating)')
                    ->from('reviews')
                    ->join('items', 'items.id', '=', 'reviews.item_id')
                    ->whereColumn('items.store_id', 'stores.id')
                    ->groupBy('items.store_id');
            }, 'avg_r')
            ->selectSub(function ($q) use ($today, $now) {
                $q->select('discount')
                    ->from('discounts')
                    ->whereColumn('discounts.store_id', 'stores.id')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->whereTime('start_time', '<=', $now)
                    ->whereTime('end_time', '>=', $now)
                    ->orderByDesc('discount')
                    ->limit(1);
            }, 'store_discount_value');

        if ($filter) {
            $query = $query->applyStoreFilter($filter);
        }

        $query = $query->orderByDesc('store_discount_value');

        $query = PersonalizationService::applyStorePersonalization($query, $user_id);

        $stores = $query->limit(max(1, $limit))
            ->offset((($offset ?? 1) - 1) * ($limit ?? 10))
            ->get();

        $new_store_days = (int) (Helpers::get_business_settings('new_store_tag_days') ?? 30);
        $new_threshold = now()->subDays($new_store_days);

        $categories_by_store = $stores->isNotEmpty()
            ? self::topCategoriesByStoreIds($stores->pluck('id')->all(), 5)
            : collect();

        $formatted = $stores->map(fn ($s) => self::format_store_for_listing($s, [
            'advertised_store_ids' => $advertised_store_ids,
            'new_threshold' => $new_threshold,
            'category_data' => $categories_by_store[$s->id] ?? [],
        ]))->values()->all();

        return [
            'total_size' => count($formatted),
            'limit' => $limit,
            'stores' => $formatted,
        ];
    }

    public static function format_store_for_listing($store, array $opts = []): array
    {
        $advertised_store_ids = $opts['advertised_store_ids'] ?? [];
        $new_threshold = $opts['new_threshold'] ?? null;
        $top_items = $opts['top_items'] ?? null;
        $with_items = $opts['with_items'] ?? ($top_items !== null);

        if ($new_threshold === null) {
            $days = (int) (Helpers::get_business_settings('new_store_tag_days') ?? 30);
            $new_threshold = now()->subDays($days);
        }

        if (array_key_exists('category_data', $opts)) {
            $category_data = is_array($opts['category_data']) ? $opts['category_data'] : [];
        } else {
            $category_data = self::topCategoriesByStoreIds([$store->id], 5)[$store->id] ?? [];
        }

        $category_ids = $category_data['ids'] ?? [];
        $category_names = $category_data['names'] ?? [];

        $delivery_time = $store->delivery_time;
        $delivery_parts = $delivery_time ? explode('-', $delivery_time) : [];
        $min_delivery = isset($delivery_parts[0]) ? (int) $delivery_parts[0] : 0;
        $max_delivery = isset($delivery_parts[1]) ? (int) preg_replace('/[^0-9]/', '', $delivery_parts[1]) : 0;

        $distance_km = isset($store->distance) ? round(((float) $store->distance) / 1000, 2) : 0;

        $top_items_array = $top_items !== null
            ? (is_array($top_items) ? $top_items : (method_exists($top_items, 'all') ? $top_items->all() : (array) $top_items))
            : [];

        $avg_discount = 0.0;
        if ($with_items && ! empty($top_items_array)) {
            $values = array_filter(array_map(fn ($i) => (float) ($i['discount'] ?? 0), $top_items_array), fn ($v) => $v > 0);
            if (! empty($values)) {
                $avg_discount = round(array_sum($values) / count($values), 2);
            }
        }

        $store_discount = null;
        if ($store->relationLoaded('discount') && $store->discount) {
            $store_discount = [
                'discount' => (float) $store->discount->discount,
                'discount_type' => $store->discount->discount_type ?? 'percent',
            ];
        }

        $reviews_count = null;
        if (isset($store->reviews_count)) {
            $reviews_count = (int) $store->reviews_count;
        } elseif (isset($store->reviews_comments_count)) {
            $reviews_count = (int) $store->reviews_comments_count;
        }

        $row = [
            'id' => (int) $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'logo_full_url' => $store->logo_full_url,
            'cover_photo_full_url' => $store->cover_photo_full_url,
            'module_id' => (int) ($store->module_id ?? 0),
            'module_type' => $store->module_type ?? null,
            'avg_rating' => (float) ($store->avg_r ?? $store->top_rated_avg ?? $store->avg_rating_all ?? $store->store_rating_avg ?? 0),
            'reviews_count' => $reviews_count,
            'items_count' => (int) ($opts['items_count'] ?? $store->items_count ?? 0),
            'category_ids' => $category_ids,
            'category_names' => $category_names,
            'delivery_time' => $delivery_time,
            'min_delivery_time' => $min_delivery,
            'max_delivery_time' => $max_delivery,
            'distance' => (float) ($store->distance ?? 0),
            'distance_km' => $distance_km,
            'open' => (int) ($store->open ?? 0),
            'current_opening_time' => Helpers::getNextOpeningTime($store->schedules) ?? 'closed',
            'active' => (int) $store->active,
            'free_delivery' => (int) ($store->free_delivery ?? 0),
            'is_new' => (int) ($store->created_at && $store->created_at->greaterThanOrEqualTo($new_threshold)),
            'ad' => (int) (in_array($store->id, $advertised_store_ids)),
            'avg_item_discount_percentage' => $avg_discount,
            'store_discount' => $store_discount,
            'offers' => self::collect_store_offers($store),
            'verified_seller' => (int) ($store->storeConfig?->verified_seller ?? 0),
        ];

        if ($with_items) {
            $row['top_items'] = $top_items_array;
        }

        return $row;
    }

    public static function topCategoriesByStoreIds(array $storeIds, int $limit = 5): \Illuminate\Support\Collection
    {
        $storeIds = array_values(array_unique(array_filter(array_map('intval', $storeIds))));
        if (empty($storeIds)) {
            return collect();
        }

        $rows = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('order_details', 'order_details.item_id', '=', 'items.id')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->selectRaw('items.store_id, CAST(categories.id AS UNSIGNED) as id, categories.parent_id, categories.name, COUNT(order_details.id) as order_count')
            ->whereIn('items.store_id', $storeIds)
            ->where('categories.status', 1)
            ->whereNotIn('orders.order_status', ['failed', 'canceled'])
            ->groupBy('items.store_id', 'id', 'categories.parent_id', 'categories.name')
            ->orderByDesc('order_count')
            ->get();

        return $rows->groupBy('store_id')
            ->map(fn ($group) => self::extract_top_category_data($group->take(max(1, $limit))));
    }

    private static function extract_top_category_data($rows): array
    {
        $category_ids = [];
        $category_names = [];
        foreach ($rows as $row) {
            if ($row->id != 0) {
                $category_ids[] = (int) $row->id;
                $category_names[] = $row->name;
            }
            if ($row->parent_id != 0) {
                $category_ids[] = (int) $row->parent_id;
            }
        }

        return [
            'ids' => array_values(array_unique($category_ids)),
            'names' => array_values(array_unique($category_names)),
        ];
    }

    public static function collect_store_offers($store): array
    {
        $offers = [];

        $flashSales = FlashSale::active()
            ->running()
            ->whereHas('products.item', fn ($q) => $q->where('store_id', $store->id))
            ->get(['id', 'title']);

        foreach ($flashSales as $fs) {
            $name = $fs->title;
            if (! $name) {
                continue;
            }
            $offers[] = [
                'type' => 'flash_sale',
                'id' => (int) $fs->id,
                'name' => $name,
            ];
        }

        $campaigns = ItemCampaign::where('store_id', $store->id)
            ->active()
            ->running()
            ->get(['id', 'title']);

        foreach ($campaigns as $c) {
            $name = $c->title;
            if (! $name) {
                continue;
            }
            $offers[] = [
                'type' => 'item_campaign',
                'id' => (int) $c->id,
                'name' => $name,
            ];
        }

        return $offers;
    }

    public static function add_store_main_categories($stores)
    {
        $store_ids = collect($stores)->pluck('id')->filter()->unique()->values()->all();
        if (empty($store_ids)) {
            return $stores;
        }

        $items = Item::whereIn('store_id', $store_ids)->active()
            ->without(['translations', 'storage', 'storeCategory'])
            ->get(['store_id', 'category_ids', 'order_count']);

        $store_category_orders = [];
        $all_ids = [];
        foreach ($items as $item) {
            foreach (Helpers::decodeJsonToArray($item['category_ids']) as $value) {
                if ((int) data_get($value, 'position') === 1) {
                    $category_id = (int) data_get($value, 'id');
                    $store_category_orders[$item['store_id']][$category_id] = ($store_category_orders[$item['store_id']][$category_id] ?? 0) + (int) $item['order_count'];
                    $all_ids[$category_id] = true;
                }
            }
        }

        $category_names = Category::whereIn('id', array_keys($all_ids))->get(['id', 'name'])->pluck('name', 'id');

        foreach ($stores as $store) {
            $order_counts = $store_category_orders[$store['id']] ?? [];
            arsort($order_counts);
            $categories = [];
            foreach (array_slice(array_keys($order_counts), 0, 5) as $category_id) {
                $categories[] = [
                    'id' => $category_id,
                    'name' => $category_names[$category_id] ?? 'NA',
                ];
            }
            $store['categories'] = $categories;
        }

        return $stores;
    }
}
