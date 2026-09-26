<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryAddRequest;
use App\Http\Requests\StoreCategoryUpdateRequest;
use App\Models\Item;
use App\Models\StoreCategory;
use App\Services\StoreCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreCategoryController extends Controller
{
    public function __construct(protected StoreCategoryService $service)
    {
    }

    public function list(Request $request): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $limit = (int) ($request->query('limit', 25));
        $offset = (int) ($request->query('offset', 1));

        $paginator = $this->service->list(
            filters: [
                'store_id' => $this->storeId($request),
                'search' => $request->search,
                'priority' => $request->priority,
            ],
            perPage: $limit,
            page: $offset
        );

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'categories' => $paginator->items(),
        ], 200);
    }

    public function details(Request $request, $id): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $category = $this->ownedQuery($request)
            ->withoutGlobalScope('translate')
            ->with('translations')
            ->find($id);

        if (!$category) {
            return $this->notFound();
        }

        return response()->json($category, 200);
    }

    public function store(StoreCategoryAddRequest $request): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $translations = $this->parseTranslations($request->translations);
        $defaultName = $this->defaultName($translations);
        if (!$defaultName) {
            return response()->json(['errors' => [['code' => 'name', 'message' => translate('messages.default_name_is_required')]]], 403);
        }

        $category = $this->service->create(
            storeId: $this->storeId($request),
            name: $defaultName,
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveApiTranslations($category, $translations);

        return response()->json(['message' => translate('messages.Store_category_added_successfully'), 'category' => $category], 200);
    }

    public function update(StoreCategoryUpdateRequest $request, $id): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $category = $this->ownedQuery($request)->find($id);
        if (!$category) {
            return $this->notFound();
        }

        $translations = $this->parseTranslations($request->translations);
        $defaultName = $this->defaultName($translations) ?? $category->getRawOriginal('name');

        $this->service->update(
            category: $category,
            name: $defaultName,
            priority: $request->filled('priority') ? (int) $request->priority : null,
            image: $request->file('image')
        );
        $this->service->saveApiTranslations($category, $translations);

        return response()->json(['message' => translate('messages.Store_category_updated_successfully'), 'category' => $category], 200);
    }

    public function status(Request $request): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $category = $this->ownedQuery($request)->find($request->id);
        if (!$category) {
            return $this->notFound();
        }
        $this->service->updateStatus($category, (int) $request->status);

        return response()->json(['message' => translate('messages.Store_category_status_updated')], 200);
    }

    public function priority(Request $request): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'priority' => 'required|integer|in:0,1,2',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $category = $this->ownedQuery($request)->find($request->id);
        if (!$category) {
            return $this->notFound();
        }
        $this->service->updatePriority($category, (int) $request->priority);

        return response()->json(['message' => translate('messages.Store_category_priority_updated')], 200);
    }

    public function delete(Request $request): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $validator = Validator::make($request->all(), ['id' => 'required']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $category = $this->ownedQuery($request)->find($request->id);
        if (!$category) {
            return $this->notFound();
        }
        $this->service->delete($category);

        return response()->json(['message' => translate('messages.Store_category_removed_successfully')], 200);
    }

    /**
     * GET /vendor/store-category/assignable-items/{id}
     * Returns items that are either uncategorized OR already assigned to this category,
     * scoped to the logged-in vendor's store. Used by the "Select Items For Category"
     * off-canvas in the mobile vendor app.
     *
     * Query params (all optional):
     *   - search: filter by name or id
     *   - limit:  page size (default 25)
     *   - offset: page number (default 1)
     */
    public function assignableItems(Request $request, $id): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $category = $this->ownedQuery($request)->find($id);
        if (!$category) {
            return $this->notFound();
        }

        $storeId = $this->storeId($request);

        $moduleType = $request['vendor']->stores[0]->module_type ?? null;
        $isService  = $this->isServiceModule($request);
        $model      = $this->bindableModel($request);

        $limit  = (int) $request->query('limit', 25);
        $offset = (int) $request->query('offset', 1);
        if ($limit < 1)  { $limit = 25; }
        if ($offset < 1) { $offset = 1; }

        $paginator = $this->queryAssignableItems($storeId, (int) $category->id, $request->query('search'), $model)
            ->paginate($limit, ['*'], 'page', $offset);

        $unassignedCount = $model::query()
            ->where('store_id', $storeId)
            ->whereNull('store_category_id')
            ->count();

        $items = collect($paginator->items())->map(function ($item) use ($category, $moduleType, $isService) {
            if ($isService) {
                $variations = $item->variations;
                $imageUrl   = $item->thumbnail_full_url;
                $price      = $item->base_price;
            } else {
                $variations = $moduleType === 'food' ? $item->food_variations : $item->variations;
                $imageUrl   = $item->image_full_url;
                $price      = $item->price;
            }
            $variations = is_string($variations) ? json_decode($variations, true) : $variations;
            $variationsCount = is_array($variations) ? count($variations) : 0;

            return [
                'id'                  => (int) $item->id,
                'name'                => $item->name,
                'image_full_url'      => $imageUrl,
                'price'               => $price,
                'store_category_id'   => $item->store_category_id ? (int) $item->store_category_id : null,
                'is_assigned'         => ((int) $item->store_category_id === (int) $category->id),
                'variations_count'    => $variationsCount,
            ];
        });

        return response()->json([
            'category' => [
                'id'   => (int) $category->id,
                'name' => $category->name,
            ],
            'unassigned_count' => $unassignedCount,
            'total_size'       => $paginator->total(),
            'limit'            => $limit,
            'offset'           => $offset,
            'items'            => $items,
        ], 200);
    }

    public function assignItems(Request $request): JsonResponse
    {
        if ($block = $this->guard($request)) {
            return $block;
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer',
            'item_ids'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $category = $this->ownedQuery($request)->find($request->category_id);
        if (!$category) {
            return $this->notFound();
        }

        $storeId = $this->storeId($request);
        $model   = $this->bindableModel($request);

        $itemIds = $request->input('item_ids', []);
        if (\is_string($itemIds)) {
            $itemIds = json_decode($itemIds, true) ?: [];
        }

        $submittedIds = collect($itemIds)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowedNewIds = [];
        if (!empty($submittedIds)) {
            $allowedNewIds = $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $submittedIds)
                ->where(function ($q) use ($category) {
                    $q->whereNull('store_category_id')
                      ->orWhere('store_category_id', $category->id);
                })
                ->pluck('id')
                ->all();
        }

        if (!empty($allowedNewIds)) {
            $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $allowedNewIds)
                ->update(['store_category_id' => $category->id]);
        }

        $model::query()
            ->where('store_id', $storeId)
            ->where('store_category_id', $category->id)
            ->when(!empty($allowedNewIds), fn ($q) => $q->whereNotIn('id', $allowedNewIds))
            ->update(['store_category_id' => null]);

        return response()->json([
            'message'        => $this->isServiceModule($request)
                ? translate('messages.Services_assigned_successfully')
                : translate('messages.Items_assigned_successfully'),
            'assigned_count' => count($allowedNewIds),
        ], 200);
    }

    private function queryAssignableItems(int $storeId, int $categoryId, ?string $search = null, string $model = Item::class)
    {
        return $model::query()
            ->where('store_id', $storeId)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('store_category_id')
                  ->orWhere('store_category_id', $categoryId);
            })
            ->when($search, function ($q) use ($search) {
                $term = '%' . trim($search) . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                          ->orWhere('id', 'like', $term);
                });
            })
            ->orderByDesc('id');
    }

    private function guard(Request $request): ?JsonResponse
    {
        if (!Helpers::vendorCategoryStatus()) {
            return response()->json(['errors' => [['code' => 'disabled', 'message' => translate('messages.Store_category_feature_is_disabled')]]], 403);
        }
        if (empty($request['vendor']->stores) || !isset($request['vendor']->stores[0])) {
            return response()->json(['errors' => [['code' => 'no-store', 'message' => translate('messages.no_store_found')]]], 403);
        }
        return null;
    }

    private function storeId(Request $request): int
    {
        return (int) $request['vendor']->stores[0]->id;
    }

    private function isServiceModule(Request $request): bool
    {
        return (($request['vendor']->stores[0]->module_type ?? null) === 'service') && addon_published_status('Service');
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function bindableModel(Request $request): string
    {
        return $this->isServiceModule($request) ? \Modules\Service\Entities\Service::class : Item::class;
    }

    private function ownedQuery(Request $request)
    {
        return StoreCategory::where('store_id', $this->storeId($request));
    }

    private function parseTranslations($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        return is_string($raw) ? (json_decode($raw, true) ?? []) : [];
    }

    private function defaultName(array $translations): ?string
    {
        $default = collect($translations)->firstWhere('locale', 'default');
        return $default['value'] ?? ($translations[0]['value'] ?? null);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['errors' => [['code' => 'store-category-404', 'message' => translate('messages.Not_found')]]], 404);
    }


        public function getProducts(Request $request, $id)
    {
        if (!Helpers::vendorCategoryStatus()) {
            return response()->json([] , 200);
        }

        $limit = $request->limit ?? 25;
        $offset = $request->offset ?? 1;

        $paginator = Item::where('store_category_id', $id)
            ->where('store_id', $this->storeId($request))
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => Helpers::product_data_formatting($paginator->items(), true, false, app()->getLocale()),
        ], 200);
    }

}
