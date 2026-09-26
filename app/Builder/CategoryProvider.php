<?php

namespace App\Builder;

use App\Builder\Resources\ItemCardResource;
use App\Builder\Support\CardContext;
use App\CentralLogics\CategoryLogic;
use App\Models\Category;
use App\Models\Item;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Modules\Builder\Contracts\CategoryProvider as CategoryProviderContract;
use Modules\Builder\ValueObjects\Storefront\CategoryDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

class CategoryProvider implements CategoryProviderContract
{
    public function forScope(?StorefrontScope $scope): array
    {
        $moduleId = $scope?->moduleId;

        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'module_id', 'priority', 'image'])
            ->where('parent_id', 0)
            ->where('position', 0)
            ->where('status', 1)
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->with(['childes' => function ($query) {
                $query->select(['id', 'name', 'slug', 'parent_id', 'priority'])
                    ->where('status', 1)
                    ->orderByDesc('priority')
                    ->orderBy('name');
            }])
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        // Every direct child (any status) of the top-level categories, mapped
        // childId => parentId. The product-by-category page counts items whose
        // category is the parent OR a direct child of it regardless of the
        // child's own status, so disabled children must feed the parent's
        // aggregate even though they're left out of the chip list below.
        $childParentMap = Category::query()
            ->whereIn('parent_id', $categories->pluck('id')->all())
            ->pluck('parent_id', 'id');

        $countableIds = $categories->pluck('id')
            ->merge($childParentMap->keys())
            ->unique()
            ->values()
            ->all();

        $directCounts = $this->scopedItemBaseQuery($scope)
            ->when($countableIds !== [], fn (Builder $query) => $query->whereIn('category_id', $countableIds))
            ->selectRaw('category_id, COUNT(*) as item_count')
            ->groupBy('category_id')
            ->pluck('item_count', 'category_id');

        // Map host rows into the canonical CategoryDTO (the module owns the
        // shape; the typed constructor validates), then emit toArray() for the
        // Inertia wire. Leaf children carry image=null, children=[].
        return $categories->map(function (Category $category) use ($directCounts, $childParentMap) {
            $children = $category->childes
                ->map(fn (Category $child) => new CategoryDTO(
                    id: (int) $child->id,
                    name: (string) $child->name,
                    slug: $child->slug,
                    image: null,
                    itemCount: (int) ($directCounts[$child->id] ?? 0),
                ))
                ->all();

            $childItemCount = $childParentMap
                ->filter(fn ($parentId) => (int) $parentId === (int) $category->id)
                ->keys()
                ->sum(fn ($childId) => (int) ($directCounts[$childId] ?? 0));

            return new CategoryDTO(
                id: (int) $category->id,
                name: (string) $category->name,
                slug: $category->slug,
                image: $category->image_full_url ?? null,
                itemCount: (int) ($directCounts[$category->id] ?? 0) + $childItemCount,
                children: $children,
            );
        })->map(fn (CategoryDTO $dto) => $dto->toArray())->values()->all();
    }

    public function normalizeActiveCategoryIds(array $categoryIds = [], mixed $categoryId = null): array
    {
        return collect($categoryIds)
            ->when($categoryId !== null && $categoryId !== '', fn ($collection) => $collection->push($categoryId))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function resolveActiveCategory(array $categories, array $activeCategoryIds): ?array
    {
        return collect($categories)
            ->map(function (array $category) {
                return [
                    ...$category,
                    'children' => $category['children'] ?? [],
                ];
            })
            ->flatMap(function (array $category) {
                return collect([$category])->merge($category['children']);
            })
            ->first(fn (array $category) => \in_array((int) $category['id'], $activeCategoryIds, true));
    }

    public function categoryProducts(
        ?StorefrontScope $scope,
        array $categoryIds = [],
        int $limit = 12,
        int $offset = 1,
        string $type = 'all',
    ): array {
        $zoneId = $scope?->regionId;
        $moduleId = $scope?->moduleId;
        $limit = \max(1, \min($limit, 100));
        $offset = \max(1, $offset);

        if (! $zoneId || ! $moduleId) {
            return [
                'total_size' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'products' => [],
                'categories' => [],
            ];
        }

        $module = Module::query()->find($moduleId);

        if (! $module) {
            return [
                'total_size' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'products' => [],
                'categories' => [],
            ];
        }

        $previousModule = Config::get('module.current_module_data');

        try {
            Config::set('module.current_module_data', $module);

            $data = ! empty($categoryIds)
                ? CategoryLogic::category_products(
                    $categoryIds,
                    \json_encode([(int) $zoneId]),
                    $limit,
                    $offset,
                    $type,
                )
                : $this->allProducts(
                    zoneId: $zoneId,
                    limit: $limit,
                    offset: $offset,
                    type: $type,
                );

            $data['products'] = ItemCardResource::fromCollection(
                $data['products'] ?? [],
                CardContext::default()
            );

            return $data;
        } finally {
            Config::set('module.current_module_data', $previousModule);
        }
    }

    private function allProducts(int $zoneId, int $limit, int $offset, string $type): array
    {
        $query = Item::query()
            ->whereHas('module.zones', function ($query) use ($zoneId) {
                $query->whereIn('zones.id', [$zoneId]);
            })
            ->whereHas('store', function ($query) use ($zoneId) {
                $query->when(config('module.current_module_data'), function ($query) {
                    $query->where('module_id', config('module.current_module_data')['id'])
                        ->whereHas('zone.modules', function ($query) {
                            $query->where('modules.id', config('module.current_module_data')['id']);
                        });
                })->where('zone_id', $zoneId);
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available')
            ->active()
            ->type($type)
            ->latest();

        $itemCategories = $query->pluck('category_id')->toArray();
        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        $categories = Category::withCount(['products', 'childes'])
            ->with(['childes' => function ($query) {
                $query->withCount(['products', 'childes']);
            }])
            ->where(['position' => 0, 'status' => 1])
            ->when(config('module.current_module_data'), function ($query) {
                $query->module(config('module.current_module_data')['id']);
            })
            ->whereIn('id', array_unique($itemCategories))
            ->orderBy('priority', 'desc')
            ->get();

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items(),
            'categories' => $categories,
        ];
    }

    private function scopedItemBaseQuery(?StorefrontScope $scope, string $type = 'all'): Builder
    {
        $zoneId = $scope?->regionId;
        $moduleId = $scope?->moduleId;
        $storeId = $scope?->subTenantId;

        return Item::query()
            ->when($zoneId, function (Builder $query) use ($zoneId) {
                $query->whereHas('module.zones', function (Builder $zoneQuery) use ($zoneId) {
                    $zoneQuery->whereIn('zones.id', [$zoneId]);
                })->whereHas('store', function (Builder $storeQuery) use ($zoneId) {
                    $storeQuery->where('zone_id', $zoneId)
                        ->when(config('module.current_module_data'), function (Builder $scopedStoreQuery) {
                            $scopedStoreQuery
                                ->where('module_id', config('module.current_module_data')['id'])
                                ->whereHas('zone.modules', function (Builder $moduleQuery) {
                                    $moduleQuery->where('modules.id', config('module.current_module_data')['id']);
                                });
                        });
                });
            })
            ->when($moduleId, fn (Builder $query) => $query->where('module_id', $moduleId))
            // Single-store storefronts scope every listing to their own store
            // (see ItemProvider::baseQuery). The category card counts must do
            // the same, otherwise they include other stores' items and read
            // higher than what the product-by-category page actually lists.
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->active()
            ->type($type);
    }

    public function translatedNamesByIds(array $ids): array
    {
        $ids = collect($ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->all();
        if ($ids === []) {
            return [];
        }

        // Unfiltered by product count: the curated explore/home tabs must show
        // every configured category's translated name — including categories
        // with no live products, which forScope() intentionally drops. The name
        // is localized by Category's `translate` global scope (session locale).
        return Category::whereIn('id', $ids)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $category) => [(int) $category->id => (string) $category->name])
            ->all();
    }

    public function findIdBySlug(?StorefrontScope $scope, string $slug): ?int
    {
        $slug = \trim($slug);
        if ($slug === '') {
            return null;
        }

        $moduleId = $scope?->moduleId;

        $id = Category::query()
            ->where('slug', $slug)
            ->where('status', 1)
            ->when($moduleId, fn (Builder $query) => $query->where('module_id', $moduleId))
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function search(?StorefrontScope $scope, ?string $query, int $limit = 30): array
    {
        $moduleId = $scope?->moduleId;
        $query = \trim((string) $query);
        $limit = \max(1, \min($limit, 100));

        return Category::with('translations')
            ->where('status', 1)
            ->where('parent_id', 0)
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhereHas('translations', fn ($t) => $t->where('value', 'like', "%{$query}%")
                        );
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'image', 'module_id'])
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug ?? null,
                'image' => $cat->image_full_url ?? null,
            ])
            ->values()
            ->all();
    }
}
