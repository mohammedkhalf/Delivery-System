<?php
namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\PersonalizationService;
use App\CentralLogics\StoreLogic;
use App\Models\Advertisement;
use App\Models\Store;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdvertisementController extends Controller
{
    public function get_adds(Request $request)
    {
        if (service_api_module_active()) {
            Helpers::setZoneIds($request);
            $zone_ids = json_decode($request->header('zoneId'), true) ?? [];
            $module_id = config('module.current_module_data')['id'] ?? null;
            $cacheKey = 'advertisement_service_'.md5(implode('_', [json_encode($zone_ids), $module_id ?? 'default']));

            $advertisements = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($zone_ids, $module_id) {
                return \Modules\Service\Lib\AdvertisementLogic::running($zone_ids, $module_id);
            });

            return response()->json($advertisements, 200);
        }

        Helpers::setZoneIds($request);
        $zone_ids= $request->header('zoneId');
        $zone_ids=  json_decode($zone_ids, true)?? [];

        $cacheKey = 'advertisement_' . md5(implode('_', [
                json_encode($zone_ids),
                config('module.current_module_data')['id'] ?? 'default'
            ]));

        $Advertisement = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($zone_ids) {
            $Advertisement = Advertisement::valid()
                ->when(config('module.current_module_data'), function($query) {
                    $query->where('module_id', config('module.current_module_data')['id']);
                })
                ->with('store')
                ->whereHas('store', function ($query) use ($zone_ids) {
                    if (!empty($zone_ids)) {
                        $query->whereIn('zone_id', $zone_ids);
                    }
                    $query->active();
                })
                ->orderByRaw('ISNULL(priority), priority ASC')
                ->get();

            try {
                $Advertisement->each(function ($advertisement) {
                    $advertisement->reviews_comments_count = (int) $advertisement?->store?->reviews_comments()->count();
                    $reviewsInfo = $advertisement?->store?->reviews()
                        ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, items.store_id')
                        ->groupBy('items.store_id')
                        ->first();

                    $advertisement->average_rating = (float)  $reviewsInfo?->average_rating ?? 0;
                });
            } catch (\Exception $e) {
                info($e->getMessage());
            }

            return $Advertisement;
        });

        // Personalize ad order for authenticated users
        if(auth('api')->check()){
            $Advertisement = PersonalizationService::reorderByPreference($Advertisement, auth('api')->id(), 'store_id', 'store');
        }

        $this->attachStoreFormatting($Advertisement);

        return response()->json($Advertisement, 200);
    }

    private function attachStoreFormatting($advertisements): void
    {
        if (! $advertisements || $advertisements->isEmpty()) {
            return;
        }

        $store_ids = $advertisements->pluck('store.id')->filter()->unique()->values()->all();
        $top_items_by_store = Store::topItemsByIds($store_ids, 3);
        $items_count_by_store = Store::activeItemCountsByIds($store_ids);
        $categories_by_store = StoreLogic::topCategoriesByStoreIds($store_ids, 5);

        $advertisements->each(function ($advertisement) use ($top_items_by_store, $items_count_by_store, $categories_by_store) {
            $store = $advertisement->store;
            if (! $store) {
                return;
            }

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

            $item_count = (int) ($items_count_by_store[(int) $store->id] ?? 0);

            $formatted_store = StoreLogic::format_store_for_listing($store, [
                'top_items' => $top_items,
                'with_items' => true,
                'items_count' => $item_count,
                'category_data' => $categories_by_store[(int) $store->id] ?? [],
            ]);
            $formatted_store['item_count'] = $item_count;

            $advertisement->unsetRelation('store');
            $advertisement->setAttribute('store', $formatted_store);
        });
    }
}
