<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Advertisement;
use App\Models\Item;
use App\Models\Module;
use App\Models\Category;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\PersonalizationService;
use App\CentralLogics\StoreLogic;
use App\CentralLogics\CategoryLogic;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Services\TrendingSearchService;
use App\Traits\ItemFilter;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    use ItemFilter;

    public function __construct(protected TrendingSearchService $trendingService) {}

    public function getTrendingSearches(Request $request)
    {
        Helpers::setZoneIds($request);
        $zoneId = $request->header('zoneId');
        $isGlobal = $request->boolean('is_global');

        if (!$isGlobal && !config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $moduleValue = $request->header('moduleId');
            $resolvedModule = is_numeric($moduleValue)
                ? Module::where('id', $moduleValue)->first()
                : Module::where('slug', $moduleValue)->first();
            if ($resolvedModule) {
                config(['module.current_module_data' => $resolvedModule]);
            }
        }

        $moduleId = config('module.current_module_data')['id'] ?? null;

        if (! $isGlobal && ! is_numeric($moduleId)) {
            return response()->json(['trending_searches' => []], 200);
        }

        $cache = filter_var($request->query('cache', true), FILTER_VALIDATE_BOOLEAN);
        $moduleIdForResponse = $isGlobal ? null : (int) $moduleId;

        $trending = array_map(fn ($keyword) => [
            'keyword' => $keyword,
            'module_id' => $moduleIdForResponse,
        ], $this->trendingService->getTrending($moduleIdForResponse, (string) $zoneId, $cache));

        return response()->json([
            'trending_searches' => $trending,
        ], 200);
    }

    public function get_combined_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_type' => 'required|in:item,store',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $data_type = $request->query('data_type', 'all');
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);

        $longitude = (float) $request->header('longitude') ?? 0;
        $latitude = (float) $request->header('latitude') ?? 0;
        $filter = $request->query('filter', '');
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, "[]"), ',')) : '';
        $rating_count = $request->query('rating_count');

        $items = $this->buildCombinedItemData($request, $zone_id, $data_type, $type, $limit, $offset, $filter, $rating_count);
        if ($items instanceof \Illuminate\Http\JsonResponse) {
            return $items;
        }

        $stores = $this->buildCombinedStoreData($request, $zone_id, $data_type, $type, $limit, $offset, $longitude, $latitude, $filter, $rating_count);
        if ($stores instanceof \Illuminate\Http\JsonResponse) {
            return $stores;
        }

        $totalCountItem = $items['total_size'] ?? 0;
        $totalCountStore = $stores['total_size'] ?? 0;

        if ($request->list_type == 'item') {
            $items['total_count_item'] = $totalCountItem;
            $items['total_count_store'] = $totalCountStore;
            return response()->json($items, 200);
        }

        $stores['total_count_item'] = $totalCountItem;
        $stores['total_count_store'] = $totalCountStore;
        return response()->json($stores, 200);
    }

    private function buildCombinedItemData(Request $request, $zone_id, $data_type, $type, $limit, $offset, $filter, $rating_count)
    {
        $category_ids = $this->toIdArray($request->query('category_ids'));
        $brand_ids = $this->toIdArray($request->query('brand_ids'));
        $min_price = $request->query('min_price') == 0 ? 0.0001 : $request->query('min_price');
        $max_price = $request->query('max_price');
        $product_id = $request->query('product_id');

        switch ($data_type) {
            case 'searched':
                return $this->getSearchedProductsData($request);
            case 'discounted':
                $items = ProductLogic::discounted_products(zone_id:$zone_id,limit: $limit, offset:$offset,type: $type,category_ids: $category_ids,filter: $filter, min: $min_price,max: $max_price, rating_count:$rating_count,brand_ids: $brand_ids,search: $request->query('search')??null, user_id: auth('api')->id());
                break;
            case 'brand':
                $validator = Validator::make($request->all(), [
                    'brand_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }
                $items = ProductLogic::brand_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $brand_ids, $request->query('store_category_id'));
                break;
            case 'new':
                $items = ProductLogic::get_new_products($zone_id, $type, $min_price, $max_price, $product_id, $limit, $offset, $filter, $rating_count, $category_ids, $brand_ids, $request->query('store_category_id'), auth('api')->id());
                break;
            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $items = CategoryLogic::category_products($category_ids, $zone_id, $limit, $offset, $type, $filter, $min_price, $max_price, $rating_count, $brand_ids, auth('api')->id());
                break;
            default:
                $zones = ! empty($zone_id) ? (json_decode($zone_id, true) ?: []) : [];
                $module = config('module.current_module_data');

                $filters = $this->resolveSearchFilters($request, $filter);
                $filter_list = $filters['filter_list'];
                $additional_data = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

                $itemsQuery = Item::with(['module', 'store'])
                    ->active()
                    ->type($type)
                    ->whereHas('module')
                    ->whereHas('store')
                    ->when($module, fn ($q) => $q->where('module_id', $module['id']))
                    ->whereHas('store', function ($q) use ($zones, $module) {
                        if (! empty($zones) && (! $module || ! ($module['all_zone_service'] ?? false))) {
                            $q->whereIn('zone_id', $zones);
                        }
                    })
                    ->when($filter_list && in_array('coupon', $filter_list), function ($query) use ($zones) {
                        $query->whereHas('module.zones', function ($q) use ($zones) {
                            if (! empty($zones)) {
                                $q->whereIn('zones.id', $zones);
                            }
                            $q->has('activeCoupons');
                        });
                    })
                    ->select('items.*')
                    ->when($filter_list && in_array('available_now', $filter_list), function ($query) {
                        $query->where(function ($q) {
                            $currentTime = now()->format('H:i:s');
                            $q->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
                            ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
                        });
                    })
                    ->applyRating($request)
                    ->applyFilters($additional_data)
                    ->applySorting($additional_data['sort_by'])
                    ->applyPriceRange($request);

                $paginator = $itemsQuery->paginate($limit, ['*'], 'page', $offset);

                $items = [
                    'total_size' => $paginator->total(),
                    'limit' => $limit,
                    'offset' => $offset,
                    'products' => $paginator->items(),
                    'categories' => [],
                ];
        }

        $items['products'] = $this->applyExtraItemFilters($items['products'], $request);
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return $items;
    }

    private function applyExtraItemFilters($items, Request $request)
    {
        if (empty($items)) {
            return $items;
        }

        $typeValues = $this->readListParam($request->query('type'));
        if (in_array('halal', $typeValues, true)) {
            $ids = collect($items)->pluck('id')->all();
            $halalIds = Item::whereIn('items.id', $ids)->where('items.is_halal', 1)->pluck('items.id')->all();
            $keep = array_flip($halalIds);
            $items = array_values(array_filter(is_array($items) ? $items : iterator_to_array($items, false), fn ($i) => isset($keep[(int) ($i['id'] ?? 0)])));
            if (empty($items)) {
                return $items;
            }
        }

        $items = $this->applySortBy($items, $request->query('sort_by'), fn ($i) => (float) ($i['price'] ?? 0));

        $threshold = $this->ratingThreshold($request->query('rating'));
        if ($threshold > 0) {
            $items = array_values(array_filter(is_array($items) ? $items : iterator_to_array($items, false), fn ($i) => (float) ($i['avg_rating'] ?? 0) >= $threshold));
        }

        return $items;
    }

    private function applySortBy($items, $sort, callable $priceFn)
    {
        if ($sort !== 'price_low_high' && $sort !== 'price_high_low') {
            return $items;
        }
        $sorted = collect(is_array($items) ? $items : iterator_to_array($items, false))
            ->sortBy(fn ($i) => $sort === 'price_low_high' ? $priceFn($i) : -$priceFn($i))
            ->values()
            ->all();
        return $sorted;
    }

    private function readListParam($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== ''));
        }
        if ($value === null || $value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== ''));
    }

    private function buildCombinedStoreData(Request $request, $zone_id, $data_type, $type, $limit, $offset, $longitude, $latitude, $filter, $rating_count)
    {
        $storeFilter = $this->storeFilterInputs($request, ['quick_action', 'type', 'sort_by', 'rating', 'price_min', 'price_max']);

        switch ($data_type) {
            case 'searched':
                $validator = Validator::make($request->all(), ['name' => 'required']);
                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $category_ids = $this->toIdArray($request['category_ids'] ?? null);

                $filters = $this->resolveSearchFilters($request, $filter);
                $additional_data = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

                $paginator = StoreLogic::search_stores($request?->name, $zone_id, $request->category_id, $limit, $offset, $type, $longitude, $latitude, $filter, null, $category_ids, null, null, $request, $additional_data);
                break;

            case 'discounted':
                $paginator = StoreLogic::get_discounted_stores($zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count, $storeFilter);
                break;

            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }
                $paginator = CategoryLogic::category_stores($request->category_ids, $zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count, $storeFilter, auth('api')->id());
                break;

            default:
                $filter_data = $request->query('filter_data', 'all');
                $store_type = $request->query('store_type', 'all');
                $featured = $request->query('featured');
                $paginator = StoreLogic::get_stores($zone_id, $filter_data, $type, $store_type, $limit, $offset, $featured, $longitude, $latitude, $filter, $rating_count, $storeFilter);
                break;
        }

        $paginator['stores'] = $this->formatStoresWithTopItems($paginator['stores'], $zone_id);
        return $paginator;
    }

    private function formatStoresWithTopItems($stores, $zone_id = null)
    {
        if (empty($stores)) {
            return [];
        }

        $store_ids = array_values(array_filter(array_map(fn ($s) => (int) ($s['id'] ?? 0), is_array($stores) ? $stores : iterator_to_array($stores, false))));
        $top_items_by_store = \App\Models\Store::topItemsByIds($store_ids, 5);
        $categories_by_store = StoreLogic::topCategoriesByStoreIds($store_ids, 5);

        $advertised_store_ids = $this->getAdvertisedStoreIds($zone_id, $store_ids);
        $advertised_set = array_flip($advertised_store_ids);

        $formatted = collect($stores)->map(function ($store) use ($top_items_by_store, $advertised_store_ids, $categories_by_store) {
            $items = $top_items_by_store[(int) $store->id] ?? collect();
            $top_items = $items->map(function ($item) {
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

            return StoreLogic::format_store_for_listing($store, [
                'advertised_store_ids' => $advertised_store_ids,
                'top_items' => $top_items,
                'with_items' => true,
                'category_data' => $categories_by_store[(int) $store->id] ?? [],
            ]);
        })->values()->all();

        if (! empty($advertised_set)) {
            usort($formatted, function ($a, $b) use ($advertised_set) {
                $aAd = isset($advertised_set[(int) ($a['id'] ?? 0)]) ? 1 : 0;
                $bAd = isset($advertised_set[(int) ($b['id'] ?? 0)]) ? 1 : 0;
                return $bAd <=> $aAd;
            });
        }

        return $formatted;
    }

    private function getAdvertisedStoreIds($zone_id, array $store_ids): array
    {
        if (empty($store_ids)) {
            return [];
        }
        $zones = $zone_id ? (json_decode($zone_id, true) ?: []) : [];
        $module = config('module.current_module_data');

        return Advertisement::valid()
            ->whereIn('store_id', $store_ids)
            ->when($module, fn ($q) => $q->where('module_id', $module['id']))
            ->whereHas('store', function ($q) use ($zones, $module) {
                $q->Active();
                if (! empty($zones) && (! $module || ! $module['all_zone_service'])) {
                    $q->whereIn('zone_id', $zones);
                }
            })
            ->pluck('store_id')
            ->unique()
            ->values()
            ->all();
    }

    private function getSearchedProductsData(Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        if(auth('api')->check()){
            PersonalizationService::recordSearchAction(auth('api')->id(), $request['name'], $request->header('moduleId') ? (int)$request->header('moduleId') : null);
        }

        $limit = $request['limit'] ?? 10;
        $offset = $request['offset'] ?? 1;
        $category_ids = $this->toIdArray($request['category_ids'] ?? null);
        $brand_ids = $this->toIdArray($request['brand_ids'] ?? null);
        $filter = $request['filter'] ? (is_array($request['filter']) ? $request['filter'] : str_getcsv(trim($request['filter'], "[]"), ',')) : null;
        $type = $request->query('type', 'all');

        $filters = $this->resolveSearchFilters($request, $filter);
        $additional_data = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

        $items = Item::active()->type($type)
            ->select('items.*')
            ->with('store', function ($query) {
                $query->withCount(['campaigns' => function ($query) {
                    $query->Running();
                }]);
            })
            ->when($request->category_id, function ($query) use ($request) {
                $query->whereHas('category', function ($q) use ($request) {
                    return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
                });
            })
            ->when($category_ids && (count($category_ids) > 0), function ($query) use ($category_ids) {
                $query->whereHas('category', function ($q) use ($category_ids) {
                    return $q->whereIn('id', $category_ids)->orWhereIn('parent_id', $category_ids);
                });
            })
            ->when(isset($brand_ids) && (count($brand_ids) > 0), function ($query) use ($brand_ids) {
                $query->whereHas('ecommerce_item_details', function ($q) use ($brand_ids) {
                    return $q->whereHas('brand', function ($q) use ($brand_ids) {
                        return $q->whereIn('id', $brand_ids);
                    });
                });
            })
            ->when($request->store_id, function ($query) use ($request) {
                return $query->where('store_id', $request->store_id);
            })
            ->whereHas('module.zones', function ($query) use ($zone_id , $filter) {
                $query->whereIn('zones.id', json_decode($zone_id, true))
                ->when($filter&&in_array('coupon',$filter),function ($qurey){
                    return $qurey->has('activeCoupons');
                });
            })
            ->whereHas('store', function ($query) use ($zone_id) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules', function ($query) {
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->search(keywords: $request['name'], relations: [
                'translations' => 'value',
                'tags' => 'tag',
                'category.parent' => 'name',
                'category' => 'name',
                'nutritions' => 'nutrition',
                'allergies' => 'allergy',
                'generic' => 'generic_name',
                'ecommerce_item_details.brand' => 'name',
                'pharmacy_item_details.common_condition' => 'name',
            ])
            ->when($filter && in_array('available_now', $filter), function ($query) {
                $query->where(function ($q) {
                    $currentTime = now()->format('H:i:s');
                    $q->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
                    ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
                });
            })
            ->applyRating($request)
            ->applyFilters($additional_data)
            ->applySorting($additional_data['sort_by'])
            ->applyPriceRange($request);

        $items = PersonalizationService::applyItemPersonalization($items, auth('api')->id());

        $items = $items->paginate($limit, ['*'], 'page', $offset);

        $item_categories = collect($items->items())->pluck('category_id')->unique()->toArray();

        try {
            $userId = $request->user?->id ?? auth('api')->user()?->id;
            $guestId = $userId ? null : ($request['guest_id'] ?? $request->header('guestId'));
            $this->trendingService->log(
                keyword: (string) $request['name'],
                userId: $userId,
                guestId: $guestId,
                moduleId: (int) (config('module.current_module_data')['id'] ?? 0),
                zoneId: (string) $request->header('zoneId'),
                resultCount: (int) $items->total(),
            );
        } catch (\Throwable $e) {
        }

        $categories = Category::withCount(['products', 'childes'])->with(['childes' => function ($query) {
            $query->withCount(['products', 'childes']);
        }])
            ->where(['position' => 0, 'status' => 1])
            ->when(config('module.current_module_data'), function ($query) {
                $query->module(config('module.current_module_data')['id']);
            })
            ->whereIn('id', $item_categories)
            ->orderBy('priority', 'desc')->get();

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items(),
            'categories' => $categories
        ];

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        return $data;
    }

    private function toIdArray(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(
                array_map(fn ($v) => (int) $v, $value),
                fn ($v) => $v > 0
            ));
        }

        $str = trim((string) $value);
        if ($str === '') {
            return [];
        }

        if (str_starts_with($str, '[')) {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) {
                return array_values(array_filter(
                    array_map(fn ($v) => (int) $v, $decoded),
                    fn ($v) => $v > 0
                ));
            }
        }

        return array_values(array_filter(
            array_map(fn ($v) => (int) trim($v), explode(',', $str)),
            fn ($v) => $v > 0
        ));
    }
}
