<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\StoreCategory;
use App\Traits\ItemFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreCategoryController extends Controller
{
    use ItemFilter;

    public function getCategories(Request $request): JsonResponse
    {
        if (!Helpers::storeCategoryStatus()) {
            return response()->json([]);
        }

        $categories = StoreCategory::active()
            ->when(config('module.current_module_data'), function ($q) {
                $q->module(config('module.current_module_data')['id']);
            })
            ->when($request->store_id, function ($q) use ($request) {
                $q->where('store_id', $request->store_id);
            })
            ->when($request->name, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->name}%");
            })
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($categories, 200);
    }

    public function getByStore($storeId): JsonResponse
    {
        if (!Helpers::storeCategoryStatus()) {
            return response()->json([]);
        }

        $categories = StoreCategory::active()
            ->where('store_id', $storeId)
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($categories, 200);
    }
    public function getCategoriesWithItems(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (service_api_module_active()) {
            return \Modules\Service\Lib\ProviderLogic::apiCategoriesWithServices($request);
        }

        try {
            if (method_exists(Helpers::class, 'setZoneIds')) {
                Helpers::setZoneIds($request);
            }

            $zoneHeader = $request->header('zoneId');
            $zones = is_string($zoneHeader) && $zoneHeader !== '' ? json_decode($zoneHeader, true) : null;
            $moduleHeader = $request->header('moduleId');
            $moduleId = $moduleHeader ? getModuleId($moduleHeader) : (config('module.current_module_data')['id'] ?? null);
            $moduleId = is_numeric($moduleId) ? (int) $moduleId : null;

            $storeId = (int) $request->query('store_id');
            $type   = $request->query('type', 'all');

            $filters = $this->resolveSearchFilters($request);
            $sortBy  = $filters['sort_by'];
            $additionalData = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

            $searchKey       = $request->query('name') ?? $request->query('search');
            $searchRelations = [
                'translations' => 'value',
                'category'     => 'name',
                'tags'         => 'tag',
            ];

            $cacheKey = 'store_cat_items_' . md5(json_encode([
                'store'     => $storeId,
                'zones'     => $zones,
                'module'    => $moduleId,
                'type'      => $type,
                'sort'      => $sortBy,
                'filter'    => $additionalData['filter_by'],
                'search'    => $searchKey,
                'rating'    => $request->query('rating_count'),
                'min_price' => $request->query('min_price'),
                'max_price' => $request->query('max_price'),
                'locale'    => app()->getLocale(),
            ]));

            $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use (
                $request, $zones, $moduleId, $storeId, $type,
                $sortBy, $additionalData, $searchKey, $searchRelations
            ) {
                $itemsTable = (new Item)->getTable();

            $applyItemConstraints = function ($q) use ($type, $storeId, $itemsTable, $zones, $moduleId) {
                $q->active(zone_ids: $zones, module_id: $moduleId)
                  ->where("{$itemsTable}.store_id", $storeId)
                  ->type($type);
            };

            $applyItemFilters = function ($q) use ($additionalData, $request, $searchKey, $searchRelations) {
                $q->applyFilters($additionalData)
                  ->applyRating($request)
                  ->applyPriceRange($request)
                  ->when($request->rating_count && is_numeric($request->rating_count), function ($q) use ($request) {
                      $q->where('avg_rating', '<', (int) $request->rating_count + 1);
                  })
                  ->when($searchKey, function ($q) use ($searchKey, $searchRelations) {
                      $q->search($searchKey, $searchRelations);
                  });
            };

            $useStoreCategory = Helpers::storeCategoryStatus()
                && StoreCategory::active()
                    ->where('store_id', $storeId)
                    ->whereHas('items', function ($q) use ($zones, $moduleId) {
                        $q->active(zone_ids: $zones, module_id: $moduleId);
                    })
                    ->exists();

            if ($useStoreCategory) {
                $categories = StoreCategory::active()
                    ->where('store_id', $storeId)
                    ->whereHas('items', function ($q) use ($zones, $moduleId) {
                        $q->active(zone_ids: $zones, module_id: $moduleId);
                    })
                    ->orderBy('priority', 'desc')
                    ->orderBy('name', 'asc')
                    ->get(['id', 'name', 'image', 'priority', 'status']);

                $categoryIds      = $categories->pluck('id')->map(fn ($v) => (int) $v)->all();
                $categoryKeyCol   = "{$itemsTable}.store_category_id";
                $baseItemScope    = function ($q) use ($itemsTable, $categoryIds) {
                    $q->whereIn("{$itemsTable}.store_category_id", $categoryIds);
                };
            } else {
                $usedCategoryIds = Item::query()
                    ->tap($applyItemConstraints)
                    ->whereNotNull("{$itemsTable}.category_id")
                    ->distinct()
                    ->pluck("{$itemsTable}.category_id")
                    ->filter()
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $categories = empty($usedCategoryIds)
                    ? collect()
                    : Category::where('status', 1)
                        ->whereIn('id', $usedCategoryIds)
                        ->orderBy('name', 'asc')
                        ->get(['id', 'name', 'image']);

                $categoryIds    = $categories->pluck('id')->map(fn ($v) => (int) $v)->all();
                $categoryKeyCol = "{$itemsTable}.category_id";
                $baseItemScope  = function ($q) use ($itemsTable, $categoryIds) {
                    if (!empty($categoryIds)) {
                        $q->whereIn("{$itemsTable}.category_id", $categoryIds);
                    }
                };
            }

            if (empty($categoryIds)) {
                return [
                    'total_size'         => 0,
                    'category_source'    => $useStoreCategory ? 'store_category' : 'main_category',
                    'categories'         => [],
                    'category_wise_items' => (object) [],
                ];
            }

            $itemsCountByCategory = Item::query()
                ->tap($baseItemScope)
                ->tap($applyItemConstraints)
                ->tap($applyItemFilters)
                ->reorder()
                ->select(DB::raw("{$categoryKeyCol} AS cat_group"), DB::raw('COUNT(*) AS cnt'))
                ->groupBy(DB::raw($categoryKeyCol))
                ->pluck('cnt', 'cat_group');

            $categoriesData = $categories
                ->map(function ($category) use ($itemsCountByCategory) {
                    return [
                        'id'             => (int) $category->id,
                        'name'           => $category->name,
                        'image_full_url' => $category->image_full_url ?? null,
                        'items_count'    => (int) ($itemsCountByCategory[$category->id] ?? 0),
                    ];
                })
                ->values();

            $totalItems = (int) $itemsCountByCategory->sum();

            $grouped = [];

            $groupColumn = $useStoreCategory ? 'store_category_id' : 'category_id';

            $items = Item::query()
                ->tap($baseItemScope)
                ->tap($applyItemConstraints)
                ->tap($applyItemFilters)
                ->with([
                    'store' => fn ($q) => $q->with(['storeConfig', 'module', 'storage']),
                ])
                ->reorder()
                ->addSelect("{$itemsTable}.*")
                ->orderBy("{$itemsTable}.name", 'asc')
                ->orderBy("{$itemsTable}.id", 'asc')
                ->applySorting($sortBy)
                ->get();

            $store      = $items->first()?->store;
            $storeName  = $store?->name;
            $storeSlug  = $store?->slug;
            $storeLogo  = $store?->logo_full_url;
            $storeFree  = $store?->free_delivery;
            $moduleType = $store?->module_type;
            $halalTag   = (int) ($store?->storeConfig?->halal_tag_status ?? 0);
            $verified   = $store ? Helpers::get_verified_seller_status($store, $store->storeConfig) : 0;

            $decode = function ($val) {
                if (is_array($val)) {
                    return $val;
                }
                if (!is_string($val) || $val === '') {
                    return [];
                }
                $decoded = json_decode($val, true);

                return is_array($decoded) ? $decoded : [];
            };

            $mapItem = function ($it) use (
                $decode, $moduleType, $storeName, $storeSlug, $storeLogo,
                $storeFree, $halalTag, $verified
            ) {
                $variations = array_map(fn ($v) => [
                    'variant_key' => $v['type'] ?? null,
                    'name'        => $v['type'] ?? null,
                    'price'       => (float) ($v['price'] ?? 0),
                    'stock'       => (int) ($v['stock'] ?? 0),
                ], $decode($it->variations));

                $foodVariations = $decode($it->food_variations);
                $hasVariant     = $moduleType === 'food' ? count($foodVariations) : count($variations);

                return [
                    'id'          => (int) $it->id,
                    'name'        => $it->name,
                    'module_type' => $moduleType,
                    'store_id'    => (int) $it->store_id,
                    'store_slug'  => $storeSlug,

                    'image_full_url' => $it->image_full_url,

                    'price'                 => (float) $it->price,
                    'base_price'            => (float) $it->price,
                    'discount'              => (float) $it->discount,
                    'discount_type'         => $it->discount_type,
                    'available_date_starts' => null,

                    'variations'            => $variations,
                    'food_variations'       => $foodVariations,
                    'has_variant'           => (int) $hasVariant,
                    'stock'                 => (int) $it->stock,
                    'maximum_cart_quantity' => (int) ($it->maximum_cart_quantity ?? 0),

                    'halal_tag_status' => $halalTag,
                    'is_halal'         => (int) $it->is_halal,
                    'organic'          => (int) $it->organic,
                    'veg'              => (int) $it->veg,
                    'avg_rating'       => (float) $it->avg_rating,
                    'rating_count'     => (int) $it->rating_count,

                    'available_time_starts' => $it->available_time_starts,
                    'available_time_ends'   => $it->available_time_ends,
                    'status'                => (int) $it->status,

                    'store_name'          => $storeName,
                    'store_logo_full_url' => $storeLogo,
                    'store' => [
                        'free_delivery'   => $storeFree,
                        'logo_full_url'   => $storeLogo,
                        'verified_seller' => $verified,
                    ],
                    'store_details' => [
                        'logo_full_url'   => $storeLogo,
                        'verified_seller' => $verified,
                    ],
                ];
            };

            $allItems = $items->groupBy($groupColumn);

            foreach ($categoryIds as $catId) {
                $catItems = $allItems[$catId] ?? null;

                if ($catItems && $catItems->isNotEmpty()) {
                    $grouped[(string) $catId] = $catItems->map($mapItem)->values();
                }
            }

            $formattedItems = (object) $grouped;

                return [
                    'total_size'         => $totalItems,
                    'category_source'    => $useStoreCategory ? 'store_category' : 'main_category',
                    'categories'         => $categoriesData,
                    'category_wise_items' => $formattedItems,
                ];
            });

            return response()->json($payload, 200);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 200);
        }
    }
}
