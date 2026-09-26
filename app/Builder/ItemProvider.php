<?php

namespace App\Builder;

use App\Builder\Resources\FoodDetailsResource;
use App\Builder\Resources\ItemDetailResource;
use App\Builder\Resources\ItemCardResource;
use App\Builder\Support\CardContext;
use App\Models\Category;
use App\Models\Item;
use App\Models\VisitorLog;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\ItemProvider as ItemProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\StorefrontScope;

class ItemProvider implements ItemProviderContract
{
    // `variations`, `food_variations`, `add_ons` are needed by ItemCardResource
    // so the storefront card knows whether to open the variation/food modal
    // instead of instant-adding. Excluding them silently breaks the routing.
    private const SELECT = ['id', 'name', 'slug', 'image', 'avg_rating', 'rating_count', 'price', 'discount', 'discount_type', 'veg', 'category_id', 'store_id', 'module_id', 'variations', 'food_variations', 'add_ons'];

    public function search(?StorefrontScope $scope, ?string $query, int $limit = 20): array
    {
        return $this->listing(
            $scope,
            [
                'search' => \trim((string) $query),
                'sort'   => 'rating',
            ],
            \max(1, \min($limit, 50)),
            1,
        )['products'];
    }

    public function discounted(?StorefrontScope $scope, int $limit = 12): array
    {
        return $this->listing(
            $scope,
            ['discounted' => true, 'sort' => 'high_to_low'],
            $limit,
            1,
        )['products'];
    }

    public function recent(?StorefrontScope $scope, int $limit = 10): array
    {
        $limit = \max(1, \min($limit, 100));
        $userId = $this->resolveUserId();

        $visitorLogSummary = VisitorLog::summary(Item::class, $userId);
        $orderColumn = $userId ? 'last_viewed_at' : 'total_view_count';

        $query = $this->baseQuery($scope, 'all')
            ->joinSub($visitorLogSummary, 'visitor_log_summary', function ($join) {
                $join->on('visitor_log_summary.visitor_log_id', '=', 'items.id');
            })
            ->select(array_map(fn ($c) => "items.$c", self::SELECT))
            ->addSelect(DB::raw('visitor_log_summary.total_view_count AS total_view_count'))
            ->addSelect(DB::raw('visitor_log_summary.last_viewed_at AS last_viewed_at'))
            ->orderByDesc($orderColumn)
            ->orderByDesc('items.created_at')
            ->limit($limit);

        return ItemCardResource::fromCollection($query->get()->all(), $this->cardContext());
    }

    public function topSelling(?StorefrontScope $scope, int $limit = 8): array
    {
        $limit = \max(1, \min($limit, 50));

        $items = $this->baseQuery($scope, 'all')
            ->popular()
            ->orderByDesc('items.created_at')
            ->limit($limit)
            ->get(self::SELECT);

        return ItemCardResource::fromCollection($items->all(), $this->cardContext());
    }

    public function listing(?StorefrontScope $scope, array $filters = [], int $limit = 12, int $offset = 1): array
    {
        $limit = \max(1, \min($limit, 100));
        $offset = \max(1, $offset);

        $query = $this->baseQuery($scope, (string) ($filters['type'] ?? 'all'));

        if (($filters['discounted'] ?? false) === true) {
            $query->discounted();
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, (string) ($filters['sort'] ?? 'high_to_low'));

        $paginator = $query->paginate($limit, self::SELECT, 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => ItemCardResource::fromCollection($paginator->items(), $this->cardContext()),
            'categories' => $this->resultCategories($scope, $paginator->items()),
        ];
    }

    public function wishlist(?StorefrontScope $scope, int $limit = 12, int $offset = 1): array
    {
        $limit = \max(1, \min($limit, 100));
        $offset = \max(1, $offset);
        $userId = $this->resolveUserId();

        if (!$userId) {
            return [
                'total_size' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'products' => [],
                'categories' => [],
            ];
        }

        $query = $this->baseQuery($scope)
            ->whereHas('whislists', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderByDesc(
                Wishlist::select('created_at')
                    ->whereColumn('item_id', 'items.id')
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at')
                    ->limit(1)
            );

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => ItemCardResource::fromCollection($paginator->items(), $this->cardContext()),
            'categories' => $this->resultCategories($scope, $paginator->items()),
        ];
    }

    public function priceRange(?StorefrontScope $scope): array
    {
        // Reflects the price extents of the active items currently in scope
        // (zone + module). Independent of any user-applied filters so the
        // slider extents stay stable as the user filters within them.
        $row = $this->baseQuery($scope)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $min = (float) ($row->min_price ?? 0);
        $max = (float) ($row->max_price ?? 0);

        return [
            'min' => (int) floor($min),
            'max' => (int) ceil($max),
        ];
    }

    public function findIdBySlug(?StorefrontScope $scope, string $slug): ?int
    {
        $slug = \trim($slug);
        if ($slug === '') {
            return null;
        }

        $id = $this->baseQuery($scope)
            ->where('slug', $slug)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function details(?StorefrontScope $scope, int $itemId): ?array
    {
        $item = $this->baseQuery($scope)
            ->whereKey($itemId)
            ->with([
                'module',
                'store.storeConfig',
                'store.discount',
                'unit',
                'rating',
                'reviews.customer',
                'pharmacy_item_details',
                'ecommerce_item_details',
                'nutritions',
                'allergies',
                'generic',
                'taxVats',
                'seoData',
                'tags',
            ])
            ->first();

        if (!$item) {
            return null;
        }

        return ItemDetailResource::fromOne($item);
    }

    /**
     * Single entry point for "fetch item view data for a modal." Returns
     * the food shape for food items and the multi-axis shape for everything
     * else; the caller dispatches off `moduleType`. This collapses what
     * used to be two separate Inertia props (`foodDetails` keyed on
     * `?food_id=` and `itemDetails` keyed on `?cart_item_id=`) into one
     * uniform contract.
     */
    public function view(?StorefrontScope $scope, int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        $item = $this->baseQuery($scope)
            ->whereKey($itemId)
            ->with([
                'module',
                'store.storeConfig',
                'store.discount',
                'unit',
                'rating',
                'pharmacy_item_details',
                'ecommerce_item_details',
                'nutritions',
                'allergies',
                'generic',
                'tags',
            ])
            ->first();

        if (!$item) {
            return null;
        }

        $this->logView($scope, (int) $item->id, (int) ($this->resolveUserId() ?? 0));

        return $item->module?->module_type === 'food'
            ? FoodDetailsResource::fromOne($item)
            : ItemDetailResource::fromOne($item->loadMissing(['reviews.customer', 'taxVats', 'seoData']));
    }

    public function logView(?StorefrontScope $scope, int $itemId, int $customerId): void
    {
        if ($itemId <= 0 || $customerId <= 0) {
            return;
        }

        \App\CentralLogics\Helpers::visitor_log(
            model: 'item',
            user_id: $customerId,
            visitor_log_id: $itemId,
            order_count: false,
        );
    }

    public function foodDetails(?StorefrontScope $scope, int $itemId): ?array
    {
        $item = $this->baseQuery($scope)
            ->whereKey($itemId)
            ->with([
                'module',
                'store.storeConfig',
                'store.discount',
                'unit',
                'rating',
                'pharmacy_item_details',
                'ecommerce_item_details',
                'nutritions',
                'allergies',
                'generic',
                'tags',
            ])
            ->first();

        if (!$item) {
            return null;
        }

        // Restrict to food module: every storefront food-modal call MUST be
        // for an item served by a food module.
        $moduleType = $item->module?->module_type;
        if ($moduleType !== 'food') {
            return null;
        }

        return FoodDetailsResource::fromOne($item);
    }

    public function similar(?StorefrontScope $scope, int $itemId, int $limit = 6): array
    {
        $item = $this->baseQuery($scope)->whereKey($itemId)->first();

        if (!$item) {
            return [];
        }

        $items = $this->baseQuery($scope)
            ->whereKeyNot($itemId)
            ->where(function (Builder $query) use ($item) {
                $query->where('category_id', $item->category_id)
                    ->orWhere('store_id', $item->store_id);
            })
            ->orderByDesc('avg_rating')
            ->latest()
            ->limit(\max(1, \min($limit, 20)))
            ->get(self::SELECT);

        return ItemCardResource::fromCollection($items, $this->cardContext());
    }

    public function listReviews(?StorefrontScope $scope, int $itemId, int $page = 1, int $perPage = 10): array
    {
        // Verify the item is in scope (so a customer can't enumerate
        // reviews for items outside the active store/zone).
        $exists = $this->baseQuery($scope)->whereKey($itemId)->exists();
        if (!$exists) {
            return ['reviews' => [], 'page' => $page, 'perPage' => $perPage, 'total' => 0, 'hasMore' => false];
        }

        $page    = \max(1, $page);
        $perPage = \max(1, \min($perPage, 50));

        $query = \App\Models\Review::query()
            ->where('item_id', $itemId)
            ->where('status', 1)
            ->latest()
            ->with([
                // Mirror ItemDetailResource::detailReviews so the
                // reviewer identity surfaces across tenants and the
                // store reply attribution resolves.
                'customer' => fn ($q) => $q->withoutGlobalScope(\App\Scopes\HostScope::class),
                'store:id,name',
            ]);

        $total   = (clone $query)->count();
        $reviews = $query->forPage($page, $perPage)->get();
        $loaded  = ($page - 1) * $perPage + $reviews->count();

        return [
            'reviews' => $reviews->map(fn ($r) => \App\Builder\Resources\ItemDetailResource::reviewRow($r))->all(),
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'hasMore' => $loaded < $total,
        ];
    }

    public function byCategories(?StorefrontScope $scope, array $categoryIds, int $limit = 12): array
    {
        $result = [];

        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            if ($categoryId <= 0) {
                continue;
            }

            $result[$categoryId] = $this->listing(
                $scope,
                ['categoryIds' => [$categoryId], 'sort' => 'rating'],
                $limit,
                1,
            )['products'];
        }

        return $result;
    }

    private function baseQuery(?StorefrontScope $scope, string $type = 'all'): Builder
    {
        $moduleId = $scope?->moduleId;
        $storeId  = $scope?->subTenantId;
        $zoneIds  = $scope?->regionId ? [(int) $scope->regionId] : null;

        // Eager-load `module` (resource reads module_type for routing) and
        // `store.discount` (ItemPricing applies store-wide discounts) so the
        // resource transformer doesn't trigger an N+1 query per card.
        return Item::query()
            ->with(['module', 'store.discount'])
            ->active($zoneIds, $moduleId)
            ->type($type)
            ->when($storeId, fn (Builder $q) => $q->where('store_id', $storeId));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $categoryIds = collect($filters['categoryIds'] ?? [])
            ->filter(fn ($id) => \is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $search = \trim((string) ($filters['search'] ?? ''));
        $priceMin = $filters['priceMin'] ?? null;
        $priceMax = $filters['priceMax'] ?? null;
        $rating = $filters['rating'] ?? null;
        $excludeItemId = $filters['excludeItemId'] ?? null;

        $query
            ->when($categoryIds !== [], function (Builder $builder) use ($categoryIds) {
                $builder->whereHas('category', function (Builder $categoryQuery) use ($categoryIds) {
                    $categoryQuery->where(function (Builder $nestedQuery) use ($categoryIds) {
                        $nestedQuery->whereIn('id', $categoryIds)
                            ->orWhereIn('parent_id', $categoryIds);
                    });
                });
            })
            ->when($search !== '', function (Builder $builder) use ($search) {
                $words = \preg_split('/\s+/', $search) ?: [];
                $builder->where(function (Builder $nestedQuery) use ($words) {
                    foreach ($words as $word) {
                        $nestedQuery->where(function (Builder $wordQuery) use ($word) {
                            $wordQuery->where('name', 'like', '%' . $word . '%')
                                ->orWhereHas('translations', function (Builder $translationQuery) use ($word) {
                                    $translationQuery->where('value', 'like', '%' . $word . '%');
                                });
                        });
                    }
                });
            })
            ->when(\is_numeric($priceMin), fn (Builder $builder) => $builder->where('price', '>=', (float) $priceMin))
            ->when(\is_numeric($priceMax) && (float) $priceMax >= 0, fn (Builder $builder) => $builder->where('price', '<=', (float) $priceMax))
            ->when(\is_numeric($rating), fn (Builder $builder) => $builder->where('avg_rating', '>=', (float) $rating))
            ->when(\is_numeric($excludeItemId), fn (Builder $builder) => $builder->whereKeyNot((int) $excludeItemId));
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'low_to_high' => $query->orderBy('price'),
            'a_to_z' => $query->orderBy('name'),
            'z_to_a' => $query->orderByDesc('name'),
            'rating' => $query->orderByDesc('avg_rating')->orderByDesc('rating_count'),
            'newest' => $query->latest(),
            default => $query->orderByDesc('price'),
        };
    }

    private function cardContext(): array
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return CardContext::default();
        }

        $wishlistIds = Wishlist::query()
            ->where('user_id', $userId)
            ->whereNotNull('item_id')
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $lookup = \array_fill_keys($wishlistIds, true);

        return [
            ...CardContext::default(),
            'wishlist_lookup' => static fn (int $itemId): bool => isset($lookup[$itemId]),
        ];
    }

    private function resultCategories(?StorefrontScope $scope, array $items): array
    {
        $categoryIds = collect($items)
            ->map(fn (Item $item) => (int) $item->category_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($categoryIds === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->when($scope?->moduleId, fn (Builder $query) => $query->where('module_id', $scope->moduleId))
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => $category->image_full_url ?? null,
            ])
            ->values()
            ->all();
    }
    private function resolveUserId(): ?int
    {
        return app(StorefrontContext::class)->getUserId();
    }
}
