<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Order;
use App\Models\Store;
use App\Models\Review;
use App\Models\Allergy;
use App\Models\Category;
use App\Models\Nutrition;
use App\Models\GenericName;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\PersonalizationService;
use App\Models\RecentSearch;
use App\CentralLogics\CategoryLogic;
use App\Http\Controllers\Controller;
use App\Services\TrendingSearchService;
use App\Traits\ItemFilter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Service\Lib\ProviderLogic;

class ItemController extends Controller
{
    use ItemFilter;

    public function __construct(protected TrendingSearchService $trendingService) {}

    public function get_latest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'category_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $filter = $request['filter'] ? (is_array($request['filter']) ? $request['filter'] : str_getcsv(trim($request['filter'], "[]"), ',')) : '';

        $rating_count = $request->query('rating_count');
        $store_category_id = $request->query('store_category_id');

        $items = ProductLogic::get_latest_products($zone_id, $request['limit'], $request['offset'], $request['store_id'], $request['category_id'], $type,$min,$max,$product_id,$filter,$rating_count,$store_category_id, auth('api')->id());
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_new_products(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = isset($request['limit'])?$request['limit']:50;
        $offset = isset($request['offset'])?$request['offset']:1;

        $items = ProductLogic::get_new_products($zone_id, $type,$min,$max,$product_id,$limit,$offset, null, null, null, null, auth('api')->id());
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_searched_products(Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }


        if(auth('api')->check()){
            RecentSearch::create([
                'user_id' => auth('api')->id(),
                'user_type' => 'App\Models\User',
                'keyword' => $request['name'],
                'route_name' => 'api.v1.items.search',
                'route_uri' => $request->path(),
                'route_full_url' => $request->fullUrl(),
            ]);
            PersonalizationService::recordSearchAction(auth('api')->id(), $request['name'], $request->header('moduleId') ? (int)$request->header('moduleId') : null);
        }

        $product_search_default_status = Helpers::get_business_settings('product_search_default_status') ?? 1;
        $product_search_sort_by_unavailable = Helpers::getPriorityList(name: 'product_search_sort_by_unavailable', type: 'unavailable');
        $product_search_sort_by_temp_closed = Helpers::getPriorityList(name: 'product_search_sort_by_temp_closed', type: 'temp_closed');


        $zone_id = $request->header('zoneId');

        $limit = $request['limit']??10;
        $offset = $request['offset']??1;
        $category_ids = $request['category_ids']?(is_array($request['category_ids'])?$request['category_ids']:json_decode($request['category_ids'])):'';
        $filter = $request['filter']?(is_array($request['filter'])?$request['filter']:str_getcsv(trim($request['filter'], "[]"), ',')):'';
        $type = $request->query('type', 'all');

        $filters = $this->resolveSearchFilters($request, $filter);
        $additional_data = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

        $query = Item::active()->type($type)
        ->with('store', function($query){
            $query->withCount(['campaigns'=> function($query){
                $query->Running();
            }]);
        })
        ->select(['items.*'])
        ->selectSub(function ($subQuery) {
            $subQuery->selectRaw('active as temp_available')
                ->from('stores')
                ->whereColumn('stores.id', 'items.store_id');
        }, 'temp_available');


        if ($product_search_default_status != '1'){
            if(config('module.current_module_data')['module_type']  !== 'food'){
                if($product_search_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($product_search_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }

            }

            if($product_search_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($product_search_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }
        }


        $query= $query->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($category_ids, function($query)use($category_ids){
            $query->whereHas('category',function($q)use($category_ids){
                return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
            });
        })
        ->when($request->store_category_id, function($query) use($request){
            return $query->where('store_category_id', $request->store_category_id);
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
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
        ->search(keywords: $request['name'], relations: [
            'translations' => 'value',
            'tags' => 'tag',
            'nutritions' => 'nutrition',
            'allergies' => 'allergy',
            'category.parent' => 'name',
            'category' => 'name',
            'generic' => 'generic_name',
            'ecommerce_item_details.brand' => 'name',
            'pharmacy_item_details.common_condition' => 'name',
        ])
        ->applyRating($request)
        ->applyFilters($additional_data)
        ->applySorting($additional_data['sort_by'])
        ->applyPriceRange($request);

        $query = PersonalizationService::applyItemPersonalization($query, auth('api')->id(), $filter);

        $item_categories=  $query->pluck('category_id')->toArray();
        $items = $query->paginate($limit, ['*'], 'page', $offset);

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
            // swallow — search response must not break on logging failure
        }

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

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items(),
            'categories'=>$categories
        ];

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_searched_products_suggestion(Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit']??10;
        $offset = $request['offset']??1;

        $type = $request->query('type', 'all');

        $items = Item::active()->type($type)

        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($request->store_category_id, function($query) use($request){
            return $query->where('store_category_id', $request->store_category_id);
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
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
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('nutritions',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('nutrition', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('allergies',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('allergy', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('generic',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('generic_name', 'like', "%{$value}%");
                    };
                });
            });
        })->select(['name','image'])

        ->paginate($limit, ['*'], 'page', $offset);

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items()
        ];

        return response()->json($data, 200);
    }

    public function get_popular_products(Request $request)
    {
        Helpers::setZoneIds($request);
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');

        $items = ProductLogic::popular_products($zone_id, $request['limit']??25, $request['offset']??1, $type,$category_ids, $filter, $min_price, $max_price, $rating_count,$request['search'], $request->query('store_category_id'), auth('api')->id());
        $items['products'] = Helpers::productListDataFormatting($items['products']);
        return response()->json($items, 200);
    }

    public function get_most_reviewed_products(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::most_reviewed_products($zone_id, $request['limit']??25, $request['offset']??1, $type,$category_ids, $filter ,$min_price, $max_price, $rating_count, $request['search']??null, $request->query('store_category_id'), auth('api')->id());
        $items['categories'] = $items['categories'];

        $items['products'] = Helpers::productListDataFormatting($items['products']);
        return response()->json($items, 200);
    }

    public function get_top_rated_products(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::top_rated_products($zone_id, $request['limit']??25, $request['offset']??1, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $request['search'], $request->query('store_category_id'), auth('api')->id());
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::productListDataFormatting($items['products']);

        return response()->json($items, 200);
    }

    public function get_discounted_products(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        $zone_id= $request->header('zoneId');

        $items = ProductLogic::discounted_products(zone_id:$zone_id, limit: $request['limit']??25, offset: $request['offset']??1, type: $type, category_ids: $category_ids, filter:$filter,min: $min_price, max:$max_price, rating_count:$rating_count,search:$request['search']??null, store_category_id: $request->query('store_category_id'), user_id: auth('api')->id());
        $items['products'] = Helpers::productListDataFormatting($items['products']);
        return response()->json($items, 200);
    }

    public function get_cart_suggest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');

        $type = $request->query('type', 'all');
        $recommended = $request->query('recommended');
        $items = ProductLogic::cart_suggest_products($zone_id, $request['store_id'], $request['limit'], $request['offset'], $type, $recommended, auth('api')->id());
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_product(Request $request, $id)
    {
        try {
            if ($request['campaign'] == 1) {
                $item = ItemCampaign::active()
                ->when(config('module.current_module_data'), function ($query) {
                        $query->module(config('module.current_module_data')['id']);
                    })
                    ->when(is_numeric($id), function ($qurey) use ($id) {
                        $qurey->where('id', $id);
                    })
                    ->when(!is_numeric($id), function ($qurey) use ($id) {
                        $qurey->where('slug', $id);
                    })
                    ->first();
            } else {
                $item = Item::withCount('whislists')->with(['tags', 'nutritions', 'allergies', 'reviews', 'reviews.customer'])->active()
                    ->when(config('module.current_module_data'), function ($query) {
                        $query->module(config('module.current_module_data')['id']);
                    })
                    ->when(is_numeric($id), function ($qurey) use ($id) {
                        $qurey->where('id', $id);
                    })
                    ->when(!is_numeric($id), function ($qurey) use ($id) {
                        $qurey->where('slug', $id);
                    })
                    ->first();
            }
            if (!$item) {
                return response()->json([
                    'errors' => ['code' => 'product-001', 'message' => translate('messages.item_currently_unavailable')]
                ], 404);
            }
            // Visitor Log
            if ($item && auth('api')->check()) {
                Helpers::visitor_log(
                    model: 'item',
                    user_id: auth('api')->id(),
                    visitor_log_id: $item->id,
                    order_count: false
                );
                PersonalizationService::recordItemAction(auth('api')->id(), $item->id, 'item_view');
            }

            $store = StoreLogic::get_store_details($item->store_id);
            if($store)
            {
                $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
                ->where('items.store_id', $item->store_id)
                ->where('categories.status',1)
                ->groupBy('categories','positions')
                ->get();

                $store = Helpers::store_data_formatting($store);
                $store['category_ids'] = array_map('intval', $category_ids->pluck('categories')->toArray());
                $store['category_details'] = Category::whereIn('id',$store['category_ids'])->get();
                $store['price_range']  = Item::withoutGlobalScopes()->where('store_id', $item->store_id)
                ->select(DB::raw('MIN(price) AS min_price, MAX(price) AS max_price'))
                ->get(['min_price','max_price'])->toArray();
            }
            $item = Helpers::product_data_formatting($item, false, true, app()->getLocale());
            $item['store_details'] = $store;
            return response()->json($item, 200);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
            ], 404);
        }
    }

    public function get_related_products(Request $request,$id)
    {
        Helpers::setZoneIds($request);
        $zone_id= $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_products($zone_id,$id,auth('api')->id());
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }
    public function get_related_store_products(Request $request,$id)
    {
        Helpers::setZoneIds($request);
        $zone_id= $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_store_products($zone_id,$id,auth('api')->id());
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }

    public function get_recommended(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $filter = $request->query('filter', 'all');

        $zone_id= $request->header('zoneId');

        $items = ProductLogic::recommended_items($zone_id, $request->store_id,$request['limit'], $request['offset'], $type, $filter, $request->query('store_category_id'), auth('api')->id());
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_set_menus()
    {
        try {
            $items = Helpers::product_data_formatting(Item::active()->with(['rating'])->where(['set_menu' => 1, 'status' => 1])->get(), true, false, app()->getLocale());
            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!']
            ], 404);
        }
    }

    public function get_product_reviews(Request $request, $item_id)
    {
        if(isset($request['limit']) && ($request['limit'] != null) && isset($request['offset']) && ($request['offset'] != null)){

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $total = $reviews->total();
        }else{

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->get();
            $total = $reviews->count();
        }

        $storage = [];
        foreach ($reviews as $temp) {
            $temp['attachment'] = json_decode($temp['attachment']);
            $temp['item_name'] = null;
            if($temp->item)
            {
                $temp['item_name'] = $temp->item->name;
                if(count($temp->item->translations)>0)
                {
                    $translate = array_column($temp->item->translations->toArray(), 'value', 'key');
                    $temp['item_name'] = $translate['name'];
                }
            }

            unset($temp['item']);
            array_push($storage, $temp);
        }

        $breakdownRows = Review::where('item_id', $item_id)
            ->active()
            ->selectRaw('FLOOR(rating) as star, COUNT(*) as total')
            ->groupBy('star')
            ->pluck('total', 'star');

        $counts = [
            5 => (int) ($breakdownRows[5] ?? 0),
            4 => (int) ($breakdownRows[4] ?? 0),
            3 => (int) ($breakdownRows[3] ?? 0),
            2 => (int) ($breakdownRows[2] ?? 0),
            1 => (int) ($breakdownRows[1] ?? 0),
        ];
        $totalReviews = array_sum($counts);
        $weighted = ($counts[5] * 5) + ($counts[4] * 4) + ($counts[3] * 3) + ($counts[2] * 2) + ($counts[1] * 1);
        $avgRating = $totalReviews > 0 ? round($weighted / $totalReviews, 1) : 0;

        $rating_summary = [
            'avg_rating' => (float) $avgRating,
            'total_reviews' => $totalReviews,
            'breakdown' => [
                ['label' => 'excellent', 'star' => 5, 'count' => $counts[5]],
                ['label' => 'good',      'star' => 4, 'count' => $counts[4]],
                ['label' => 'average',   'star' => 3, 'count' => $counts[3]],
                ['label' => 'below',     'star' => 2, 'count' => $counts[2]],
                ['label' => 'poor',      'star' => 1, 'count' => $counts[1]],
            ],
        ];

        $data =  [
            'rating_summary' => $rating_summary,
            'total_size' => $total,
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'reviews' => $storage
        ];

        return response()->json($data, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $item = Item::find($id);
            $overallRating = ProductLogic::get_overall_rating($item->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required',
            'order_id' => 'required',
            'rating' => 'required|numeric|max:5',
        ]);

        $order = Order::find($request->order_id);
        if (isset($order) == false) {
            $validator->errors()->add('order_id', translate('messages.order_data_not_found'));
        }

        $item = Item::find($request->item_id);
        if (isset($order) == false) {
            $validator->errors()->add('item_id', translate('messages.item_not_found'));
        }

        $multi_review = Review::where(['item_id' => $request->item_id, 'user_id' => $request->user()->id, 'order_id'=>$request->order_id])->first();
        if (isset($multi_review)) {
            return response()->json([
                'errors' => [
                    ['code'=>'review','message'=> translate('messages.already_submitted')]
                ]
            ], 403);
        } else {
            $review = new Review;
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    array_push($image_array, Storage::disk('public')->put('review', $image));
                }
            }
        }

        $order?->OrderReference?->update([
            'is_reviewed' => 1
        ]);

        $review->user_id = $request->user()->id;
        $review->item_id = $request->item_id;
        $review->order_id = $request->order_id;
        $review->module_id = $order->module_id;
        $review->comment = $request?->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        PersonalizationService::recordItemAction($request->user()->id, (int)$request->item_id, 'review');

        if($item->store)
        {
            $store_rating = StoreLogic::update_store_rating($item->store->rating, (int)$request->rating);
            $item->store->rating = $store_rating;
            $item->store->save();
        }

        $item->rating = ProductLogic::update_rating($item->rating, (int)$request->rating);
        $item->avg_rating = ProductLogic::get_avg_rating(json_decode($item->rating, true));
        $item->save();
        $item->increment('rating_count');

        return response()->json(['message' => translate('messages.review_submited_successfully')], 200);
    }

    public function item_or_store_search(Request $request){

        Helpers::setZoneIds($request);
        if (!$request->hasHeader('longitude') || !$request->hasHeader('latitude')) {
            $errors = [];
            array_push($errors, ['code' => 'longitude-latitude', 'message' => translate('messages.longitude-latitude_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $isGlobal = $request->boolean('is_global');

        if (!$isGlobal && !config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $resolvedModule = getModule($request->header('moduleId'));
            if ($resolvedModule) {
                config(['module.current_module_data' => $resolvedModule]);
            }
        }

        $module = $isGlobal ? null : config('module.current_module_data');

        $service_scoped = service_api_module_active() && !$isGlobal;

        $items = $service_scoped ? collect() : Item::active()->with('module')->without(['translations', 'storeCategory'])->whereHas('store', function($query)use($zone_id, $module){
            $query->when($module, function($query)use($module){
                $query->where('module_id', $module['id'])->whereHas('zone.modules',function($query)use($module){
                    $query->where('modules.id', $module['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->when($request->store_category_id, function($query) use($request){
            return $query->where('store_category_id', $request->store_category_id);
        })
        ->search(keywords: $request['name'], relations: [
            'translations' => 'value',
            'tags' => 'tag',
            'nutritions' => 'nutrition',
            'allergies' => 'allergy',
            'category.parent' => 'name',
            'category' => 'name',
            'generic' => 'generic_name',
            'ecommerce_item_details.brand' => 'name',
            'pharmacy_item_details.common_condition' => 'name',
        ], mainCol: ['name', 'description'])
        ->applyPriceRange($request)
        ->limit(50)
        ->get(['id','name','image','module_id'])
        ->map(function ($item) {
            $mod = $item->module;
            return [
                'id' => $item->id,
                'name' => $item->name,
                'image' => $item->image,
                'image_full_url' => $item->image_full_url,
                'module_id' => $item->module_id,
                'module' => $mod ? [
                    'id' => $mod->id,
                    'name' => $mod->module_name,
                    'image' => $mod->icon_full_url,
                    'type' => $mod->module_type,
                ] : null,
            ];
        });

        $stores = Store::withOpen($longitude ?? 0, $latitude ?? 0)->with('module')->weekday()
        ->search(keywords: $request['name'], relations: [
            'translations' => 'value',
            'items.nutritions' => 'nutrition',
            'items.allergies' => 'allergy',
            'items.generic' => 'generic_name',
            'items.ecommerce_item_details.brand' => 'name',
            'items.pharmacy_item_details.common_condition' => 'name',
        ])
        ->when($module, function($query)use($zone_id, $module){
            $query->whereHas('zone.modules', function($q)use($module){
                $q->where('modules.id', $module['id']);
            })->module($module['id']);
            if(!$module['all_zone_service']) {
                $query->whereIn('zone_id', json_decode($zone_id, true));
            }
        })
        ->when(!$module, function($query)use($zone_id){
            $query->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->active()
        ->when($request->filled('min_price') || $request->filled('max_price') || $request->filled('price'), function ($query) use ($request) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('items', fn ($i) => $i->applyPriceRange($request));
                if (addon_published_status('Service')) {
                    $q->orWhereHas('services', fn ($s) => $s->applyPriceRange($request->input('min_price'), $request->input('max_price'), $request->input('price')));
                }
            });
        })
        ->limit(50)
        ->get()
        ->map(function ($store) {
            $mod = $store->module;
            return [
                'id' => $store->id,
                'name' => $store->name,
                'logo' => $store->logo,
                'logo_full_url' => $store->logo_full_url,
                'module_id' => $store->module_id,
                'open' => (int) ($store->open ?? 0),
                'distance' => isset($store->distance) ? (float) $store->distance : null,
                'distance_km' => isset($store->distance) ? round(((float) $store->distance) / 1000, 2) : null,
                'module' => $mod ? [
                    'id' => $mod->id,
                    'name' => $mod->module_name,
                    'image' => $mod->icon_full_url,
                    'type' => $mod->module_type,
                ] : null,
            ];
        });

        if (service_api_module_active() || ($isGlobal && addon_published_status('Service'))) {
            $service_module_id = $isGlobal ? null : ($module['id'] ?? null);
            $services = ProviderLogic::searchServices($request->name, json_decode($zone_id, true) ?? [], $service_module_id, request: $request);
            $items = $items->concat($services);
        }

        if(auth('api')->check()){
            PersonalizationService::recordSearchAction(auth('api')->id(), $request->name, config('module.current_module_data') ? (int)config('module.current_module_data')['id'] : null);
        }

        return [
            'items' => $items,
            'stores' => $stores
        ];

    }

    public function get_store_condition_products(Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone_id= $request->header('zoneId');

        $type = $request->query('type', 'all');
        $limit = $request['limit'];
        $offset = $request['offset'];
        $zones = !empty($zone_id) ? json_decode($zone_id, true) : null;

        $paginator = Item::query()

        ->when(empty($request->store_id), function ($query) use ($zones) {

            $query->when(!empty($zones), function ($query) use ($zones) {
                $query->whereHas('module.zones', function ($q) use ($zones) {
                    $q->whereIn('zones.id', $zones);
                });
            });

            $query->whereHas('store', function ($q) use ($zones) {

                $q->when(!empty($zones), function ($q) use ($zones) {
                    $q->whereIn('zone_id', $zones);
                });

                $q->whereHas('zone.modules', function ($q) {
                    $q->when(config('module.current_module_data'), function ($q) {
                        $q->where('modules.id', config('module.current_module_data')['id']);
                    });
                });
            });
        })

        ->when(is_numeric($request->store_id), function ($query) use ($request) {
            $query->where('store_id', $request->store_id);
        })

        ->when(!is_numeric($request->store_id), function ($query) use ($request) {
            $query->whereHas('store', function ($q) use ($request) {
                $q->where('slug', $request->store_id);
            });
        })

        ->when($request->store_category_id, function ($query) use ($request) {
            $query->where('store_category_id', $request->store_category_id);
        })

        ->whereHas('pharmacy_item_details', function ($q) {
            $q->whereNotNull('common_condition_id');
        })

        ->whereHas('ecommerce_item_details', function ($q) {
            $q->whereNotNull('brand_id');
        })

        ->active()
        ->type($type);

        $paginator = PersonalizationService::applyItemPersonalization($paginator, auth('api')->id());
        $paginator = $paginator->latest()
        ->paginate($limit, ['*'], 'page', $offset);
        $data=[
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
        $data['products'] = Helpers::product_data_formatting($data['products'] , true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_popular_basic_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = $request['limit']??25;
        $offset = $request['offset']??1;

        $items = ProductLogic::get_popular_basic_products($zone_id, $limit, $offset, $type, $request['store_id'], $request['category_id'], $min,$max,$product_id, auth('api')->id());
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_organic_products(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        $zone_id = $request->header('zoneId');

        $items = ProductLogic::organic_products($zone_id, $request['limit']??25, $request['offset']??1, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $request['search'], auth('api')->id());
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::productListDataFormatting($items['products']);

        return response()->json($items, 200);
    }

    public function get_products(Request $request)
    {
        Helpers::setZoneIds($request);
        $data_type = $request->query('data_type', 'all');

        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        // Common parameters for all product types
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');
        $product_id = $request->query('product_id');

        switch ($data_type) {
            case 'searched':
                return $this->get_searched_products($request);
                break;
            case 'discounted':
                $items = ProductLogic::discounted_products(zone_id:$zone_id,limit: $limit, offset: $offset, type: $type,  category_ids:$category_ids, filter: $filter, min: $min_price,max: $max_price,rating_count: $rating_count, search:$request['search']??null, user_id: auth('api')->id());
                break;
            case 'new':
                $items = ProductLogic::get_new_products($zone_id, $type, $min_price, $max_price, $product_id, $limit, $offset, $filter, $rating_count, null, null, $request->query('store_category_id'), auth('api')->id());
                break;
            case 'top_rated':
                $items = ProductLogic::top_rated_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $request['search'], auth('api')->id());
                break;
            case 'organic':
                $items = ProductLogic::organic_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $request['search']);
                break;
            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $items = CategoryLogic::category_products($category_ids, $zone_id, $limit, $offset, $type, $filter, $min_price, $max_price, $rating_count, null, auth('api')->id());
                break;
            default:
            $items =  [
                'total_size' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'products' => [],
                'categories' => [],
            ];
        }

        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }



    public function getGenericNameList(){
        $names= GenericName::select(['generic_name'])->pluck('generic_name');
        return response()->json($names, 200);
    }
    public function getAllergyNameList(){
        $names= Allergy::select(['allergy'])->pluck('allergy');
        return response()->json($names, 200);
    }
    public function getNutritionNameList(){
        $names= Nutrition::select(['nutrition'])->pluck('nutrition');
        return response()->json($names, 200);
    }

    public function get_recently_viewed_products(Request $request)
    {
        Helpers::setZoneIds($request);

        $type = $request->query('type', 'all');
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::recently_viewed_products($zone_id, $request['limit']??25, $request['offset']??1, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $request['search'], $request->query('store_category_id'), auth('api')->id());
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::productListDataFormatting($items['products']);

        return response()->json($items, 200);
    }

    public function getOfferItems(Request $request)
    {
        Helpers::setZoneIds($request);

        $moduleHeader = $request->header('moduleId');
        $moduleId = $moduleHeader ? getModuleId($moduleHeader) : null;
        $userId = null;
        try {
            $userId = auth('api')->user()?->id;
        } catch (\Throwable) {
            $userId = null;
        }

        $limit = max(1, (int) $request->query('limit', 20));
        $offset = max(1, (int) $request->query('offset', 1));

        $data = ProductLogic::get_offer_items(
            is_numeric($moduleId) ? (int) $moduleId : null,
            $request->header('zoneId'),
            $userId,
            $limit,
            $offset,
            $request,
        );

        return response()->json($data, 200);
    }

    public function getOfferStores(Request $request)
    {
        if (! config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $resolvedModule = getModule($request->header('moduleId'));
            if ($resolvedModule) {
                config(['module.current_module_data' => $resolvedModule]);
            }
        }

        if (service_api_module_active()) {
            return ProviderLogic::apiOffers($request);
        }

        Helpers::setZoneIds($request);

        $moduleHeader = $request->header('moduleId');
        $moduleId = $moduleHeader ? getModuleId($moduleHeader) : null;

        $limit = max(1, (int) $request->query('limit', 20));
        $offset = max(1, (int) $request->query('offset', 1));
        $q = $request->query('search');

        if (! $request->filled('quick_action') && $request->filled('store_type')) {
            $request->merge(['quick_action' => $request->query('store_type')]);
        }

        $filter = $this->storeFilterInputs($request);

        $longitude = is_numeric($request->header('longitude')) ? (float) $request->header('longitude') : 0.0;
        $latitude = is_numeric($request->header('latitude')) ? (float) $request->header('latitude') : 0.0;

        $data = ProductLogic::get_offer_stores(
            is_numeric($moduleId) ? (int) $moduleId : null,
            $request->header('zoneId'),
            $longitude,
            $latitude,
            $offset,
            $limit,
            is_string($q) ? $q : null,
            $filter,
        );

        return response()->json($data, 200);
    }

}
