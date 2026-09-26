<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Brand;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    public function get_brands(Request $request,$search=null)
    {
        Helpers::setZoneIds($request);

        $brand_default_status = Helpers::get_business_settings('brand_default_status') ?? 1;
        $brand_sort_by_general = Helpers::getPriorityList(name: 'brand_sort_by_general', type: 'general');

        $brand_item_default_status = Helpers::get_business_settings('brand_item_default_status') ?? 1;
        $brand_item_sort_by_unavailable = Helpers::getPriorityList(name: 'brand_item_sort_by_unavailable', type: 'unavailable');
        $brand_item_sort_by_temp_closed = Helpers::getPriorityList(name: 'brand_item_sort_by_temp_closed', type: 'temp_closed');
        $remove_out_of_stock = $brand_item_default_status != '1'
            && data_get(config('module.current_module_data'), 'module_type') !== 'food'
            && $brand_item_sort_by_unavailable == 'remove';
        $remove_temp_closed = $brand_item_default_status != '1' && $brand_item_sort_by_temp_closed == 'remove';

        $decoded_zone = json_decode($request->header('zoneId'), true);
        $zone_ids = is_array($decoded_zone) ? $decoded_zone : null;
        $module_id = getModuleId($request->header('moduleId'));

        $brands = Brand::Active()
        ->where(function($query) use($module_id){
            $query->whereNull('module_id')->orWhere('module_id',  $module_id);
        })
        ->withCount(['items as items_count' => function($query) use($zone_ids, $module_id, $remove_out_of_stock, $remove_temp_closed) {
            $query->select(DB::raw('count(distinct ecommerce_item_details.item_id)'))
                ->whereHas('item', function($q) use($zone_ids, $module_id, $remove_out_of_stock, $remove_temp_closed) {
                    $q->active(zone_ids: $zone_ids, module_id: $module_id)
                        ->where('module_id', $module_id)
                        ->when($remove_out_of_stock, function($q){
                            $q->where('stock', '>', 0);
                        })
                        ->when($remove_temp_closed, function($q){
                            $q->whereHas('store', function($q){
                                $q->where('active', 1);
                            });
                        });
                });
        }])
        ->when($search, function($query) use($search){
            $query->search($search);
        })
        ->when($brand_default_status  != 1 &&  $brand_sort_by_general == 'latest', function ($query) {
            $query->latest();
        })
        ->when($brand_default_status  != 1 &&  $brand_sort_by_general == 'oldest', function ($query) {
            $query->oldest();
        })
        ->when($brand_default_status  != 1 &&  $brand_sort_by_general == 'a_to_z', function ($query) {
            $query->orderby('name');
        })
        ->when($brand_default_status  != 1 &&  $brand_sort_by_general == 'z_to_a', function ($query) {
            $query->orderby('name','desc');
        })
        ->get();

        if(($brand_default_status  != 1 &&  $brand_sort_by_general == 'order_count') || $request->top == 1){
            foreach ($brands as $brand) {
                $productCountQuery = Item::active(zone_ids: $zone_ids, module_id: $module_id)
                    ->whereHas('ecommerce_item_details',function($q)use($brand){
                        return $q->whereHas('brand',function($q)use($brand){
                            return $q->when(is_numeric($brand->id),function ($qurey) use($brand){
                                return $qurey->whereId($brand->id);
                            })
                                ->when(!is_numeric($brand->id),function ($qurey) use($brand){
                                    $qurey->where('slug', $brand->id);
                                });
                        });
                    });

                $brand['order_count'] = $productCountQuery->sum('order_count');
            }

            $brands = $brands->sortByDesc('order_count')->values();
        }

        return response()->json(collect($brands)->map(fn ($brand) => [
            'id' => (int) $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'image_full_url' => $brand->image_full_url,
            'items_count' => (int) ($brand->items_count ?? 0),
        ])->values(), 200);
    }

    public function get_products($id, Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $brand_item_default_status = Helpers::get_business_settings('brand_item_default_status') ?? 1;
        $brand_item_sort_by_general = Helpers::getPriorityList(name: 'brand_item_sort_by_general', type: 'general');
        $brand_item_sort_by_unavailable = Helpers::getPriorityList(name: 'brand_item_sort_by_unavailable', type: 'unavailable');
        $brand_item_sort_by_temp_closed = Helpers::getPriorityList(name: 'brand_item_sort_by_temp_closed', type: 'temp_closed');

        $decoded_zone = json_decode($request->header('zoneId'), true);
        $zone_ids = is_array($decoded_zone) ? $decoded_zone : [];

        $type = $request->query('type', 'all');
        $limit = $request['limit'];
        $offset = $request['offset'];

        $query = Item::
        whereHas('module.zones', function($query)use($zone_ids){
            $query->whereIn('zones.id', $zone_ids);
        })
        ->when(config('module.current_module_data'), function($query){
            $query->where('module_id', config('module.current_module_data')['id']);
        })
        ->whereHas('store', function($query)use($zone_ids){
            $query->whereIn('zone_id', $zone_ids)->whereHas('zone.modules',function($query){
                $query->when(config('module.current_module_data'), function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            });
        })
        ->whereHas('ecommerce_item_details',function($q)use($id){
            return $q->whereHas('brand',function($q)use($id){
                return $q->when(is_numeric($id),function ($qurey) use($id){
                    return $qurey->whereId($id);
                })
                ->when(!is_numeric($id),function ($qurey) use($id){
                    $qurey->where('slug', $id);
                });
            });
        })
        ->select(['items.*'])
        ->selectSub(function ($subQuery) {
            $subQuery->selectRaw('active as temp_available')
                ->from('stores')
                ->whereColumn('stores.id', 'items.store_id');
        }, 'temp_available')
        ->active()->type($type);

        if ($brand_item_default_status == '1'){
            $query = $query->latest();
        } else {
            if(data_get(config('module.current_module_data'), 'module_type') !== 'food'){
                if($brand_item_sort_by_unavailable == 'remove'){
                    $query = $query->where('stock', '>', 0);
                }elseif($brand_item_sort_by_unavailable == 'last'){
                    $query = $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if($brand_item_sort_by_temp_closed == 'remove'){
                $query = $query->having('temp_available', '>', 0);
            }elseif($brand_item_sort_by_temp_closed == 'last'){
                $query = $query->orderByDesc('temp_available');
            }

            if ($brand_item_sort_by_general == 'rating') {
                $query = $query->orderByDesc('avg_rating');
            } elseif ($brand_item_sort_by_general == 'review_count') {
                $query = $query->withCount('reviews')->orderByDesc('reviews_count');

            } elseif ($brand_item_sort_by_general == 'a_to_z') {
                $query = $query->orderBy('name');
            } elseif ($brand_item_sort_by_general == 'z_to_a') {
                $query = $query->orderByDesc('name');
            } elseif ($brand_item_sort_by_general == 'order_count') {
                $query = $query->orderByDesc('order_count');
            }

        }

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);
        $data=[
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
        $data['products'] = Helpers::product_data_formatting($data['products'] , true, false, app()->getLocale());
        return response()->json($data, 200);
    }
}
