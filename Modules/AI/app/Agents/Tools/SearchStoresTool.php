<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\CentralLogics\StoreLogic;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchStoresTool implements Tool
{
    /** Generic words that signal a "top/best/popular" listing, not a literal name. */
    private const INTENT_TOP = [
        'best', 'top', 'popular', 'trending', 'highest', 'rated', 'good',
        'suggest', 'suggestions', 'recommend', 'recommended',
        'restaurants', 'restaurant', 'stores', 'store', 'shops', 'shop',
        'vendors', 'vendor', 'all', 'me',
        // common filler words so a natural phrase like "give me a vendor list"
        // is still treated as a listing, not a literal name search.
        'give', 'list', 'show', 'find', 'please', 'need', 'want', 'the', 'a', 'of', 'for',
    ];

    /** Words that specifically request a distance-based listing. */
    private const INTENT_NEAR = [
        'nearby', 'nearest', 'near', 'around', 'close', 'closest',
    ];

    /** Words that request the fastest-delivery listing. */
    private const INTENT_FAST = [
        'fast', 'fastest', 'quick', 'quickest', 'quickly', 'speedy',
        'soonest', 'express', 'rapid', 'fast-delivery',
    ];

    /** Words that request the popular / most-ordered listing. */
    private const INTENT_POPULAR = [
        'popular', 'trending', 'famous', 'best-selling', 'bestselling',
    ];

    /** Words that request the top-rated listing. */
    private const INTENT_TOP_RATED = [
        'rated', 'top-rated',
    ];

    /**
     * @param int[] $zoneIds Overlapping zones the user falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int   $moduleId  = null,
        private readonly array  $zoneIds   = [],
        private readonly ?float $latitude  = null,
        private readonly ?float $longitude = null,
        private readonly ?User  $user      = null,
    ) {}

    public function description(): string
    {
        return 'Search vendors / stores / restaurants / shops. Pass a specific name to filter by name, or leave query empty (or pass a generic phrase like "best", "top", "popular", "nearby", "fastest delivery", "suggest") to list active stores in the customer\'s zone. Listings use the SAME ranking as the storefront — promoted stores, then personalised picks, then open-now, with the requested sort (nearest by distance, fastest by delivery time, popular by orders, or top-rated). Returns each store\'s rating, delivery time, minimum order, free delivery, and open/closed status.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'    => $schema->string()->description('Specific store/vendor name to search, or a listing intent word ("nearby", "fastest", "popular", "top rated"), or null for the default top stores.')->required()->nullable(),
            'featured' => $schema->boolean()->description('true to show only featured/promoted stores, null for all')->required()->nullable(),
            'limit'    => $schema->number()->description('Number of results, default 6, or null for default')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args     = $request->all();
        $query    = trim((string) ($args['query'] ?? ''));
        $limit    = min((int) ($args['limit'] ?? 6), 10);
        $featured = ($args['featured'] ?? null) !== null ? (bool) $args['featured'] : null;

        $keywords = array_filter(array_map('trim', explode(' ', $query)));

        $hasNearWord = $this->matchesIntent($keywords, self::INTENT_NEAR);
        $hasCoords   = $this->latitude !== null && $this->longitude !== null;
        $isNearest   = $hasNearWord && $hasCoords;
        $isFast      = $this->matchesIntent($keywords, self::INTENT_FAST);
        $isPopular   = $this->matchesIntent($keywords, self::INTENT_POPULAR);
        $isTopRated  = $this->matchesIntent($keywords, self::INTENT_TOP_RATED);

        // A "listing" is anything that's not a literal store-name search:
        // empty query, an all-generic phrase, or any explicit listing intent.
        $isListing = $query === ''
            || $this->isGenericIntent($keywords)
            || $isNearest || $hasNearWord || $isFast || $isPopular || $isTopRated;

        if ($isListing) {
            $stores = $this->listingStores($limit, $featured, $isNearest, $isFast, $isPopular, $isTopRated);
        } else {
            $stores = $this->nameSearchStores($query, $keywords, $limit, $featured);
        }

        $storeList = $stores->map($this->format(...))->values()->all();
        $this->context->recordTool('SearchStoresTool');
        $this->context->addStores($storeList);

        if (count($storeList) === 0) {
            return $isListing
                ? 'No active stores available right now.'
                : "No stores found for \"{$query}\".";
        }

        $lines = implode('; ', array_map(function (array $s): string {
            $parts = [$s['name'] . ' [ID:' . $s['id'] . ']'];
            if ($s['avg_rating'] > 0) {
                $parts[] = '★' . number_format($s['avg_rating'], 1);
            }
            if (! empty($s['delivery_time'])) {
                $parts[] = $s['delivery_time'];
            }
            if ($s['distance_km'] !== null) {
                $parts[] = $this->formatDistance($s['distance_km']);
            }
            $parts[] = $s['is_open'] ? 'open' : 'closed';
            if ($s['free_delivery']) {
                $parts[] = 'free delivery';
            }
            return implode(' — ', $parts);
        }, $storeList));

        $label = match (true) {
            $isNearest   => 'Nearest stores',
            $isFast      => 'Fastest-delivery stores',
            $isTopRated  => 'Top-rated stores',
            $isPopular   => 'Popular stores',
            $isListing   => 'Top stores' . ($query !== '' && ! $this->isGenericIntent($keywords) ? " for \"{$query}\"" : ''),
            default      => 'store(s) found for "' . $query . '"',
        };

        // When the user asked for "nearest" but no coordinates were provided,
        // surface that so the AI can be honest about the fallback.
        $note = '';
        if ($hasNearWord && ! $hasCoords) {
            $note = ' (no GPS coordinates available — showing promoted/popular stores instead)';
        }

        return count($storeList) . ' ' . $label . $note . ': ' . $lines;
    }

    /**
     * Storefront-identical listing. Delegates to StoreLogic::get_stores — the
     * same call the storefront's store-list endpoints use — so promoted stores,
     * personalisation, open-now and the requested sort all match the app.
     *
     * Falls back to a local query only when no zone is known (get_stores does a
     * whereIn on the decoded zone list, which would return nothing for []).
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function listingStores(int $limit, ?bool $featured, bool $isNearest, bool $isFast, bool $isPopular, bool $isTopRated): \Illuminate\Support\Collection
    {
        if (empty($this->zoneIds)) {
            return $this->fallbackListing($limit, $featured, $isNearest, $isFast);
        }

        // Map the chat intent onto StoreLogic's filter / store_type vocabulary.
        $filter    = [];
        $storeType = 'all';
        if ($isNearest) {
            $filter[] = 'nearby';
        } elseif ($isFast) {
            $filter[] = 'fast_delivery';
        } elseif ($isTopRated) {
            $storeType = 'top_rated';
        } elseif ($isPopular) {
            $storeType = 'popular';
        }

        $result = StoreLogic::get_stores(
            zone_id:     json_encode(array_values($this->zoneIds)),
            filter_data: 'all',
            type:        'all',
            store_type:  $storeType,
            limit:       $limit,
            offset:      1,
            featured:    $featured ?? false,
            longitude:   $this->longitude ?? 0,
            latitude:    $this->latitude ?? 0,
            filter:      $filter ?: '',
            rating_count: null,
            store_filter: null,
            user_id:     $this->user?->getKey(),
            module_id:   $this->moduleId,
        );

        return collect($result['stores'] ?? []);
    }

    /**
     * Local listing used only when the chat has no zone context. Mirrors the
     * storefront's promoted-first / open-first ordering as closely as possible
     * without a zone-scoped StoreLogic call.
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function fallbackListing(int $limit, ?bool $featured, bool $isNearest, bool $isFast): \Illuminate\Support\Collection
    {
        return Store::WithOpenWithDeliveryTime($this->longitude ?? 0, $this->latitude ?? 0)
            ->active()
            ->whereHas('module', fn ($q) => $q->where('status', 1))
            ->withCount(['items', 'reviews', 'orders'])
            ->with(['discount' => fn ($q) => $q->validate()])
            ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
            ->when($featured, fn ($q) => $q->featured())
            ->withExists('advertisements')
            ->orderByDesc('advertisements_exists')
            ->orderByDesc('open')
            ->when($isNearest, fn ($q) => $q->orderBy('distance'))
            ->when($isFast, fn ($q) => $q->orderBy('min_delivery_time'))
            ->when(! $isNearest && ! $isFast, fn ($q) => $q->orderByDesc('orders_count')->orderBy('min_delivery_time'))
            ->limit($limit)
            ->get();
    }

    /**
     * Literal store-name (and item-name) search, zone + module scoped.
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function nameSearchStores(string $query, array $keywords, int $limit, ?bool $featured): \Illuminate\Support\Collection
    {
        return Store::WithOpenWithDeliveryTime($this->longitude ?? 0, $this->latitude ?? 0)
            ->active()
            ->whereHas('module', fn ($q) => $q->where('status', 1))
            ->withCount(['items', 'reviews', 'orders'])
            ->with(['discount' => fn ($q) => $q->validate()])
            ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
            ->when(!empty($this->zoneIds), fn ($q) => $q->whereIn('zone_id', $this->zoneIds))
            ->when($featured, fn ($q) => $q->featured())
            ->where(function ($qq) use ($keywords, $query) {
                $qq->where('name', 'like', "%{$query}%");
                foreach ($keywords as $kw) {
                    $qq->orWhere('name', 'like', "%{$kw}%");
                }
                $qq->orWhereHas('items', function ($iq) use ($query, $keywords) {
                    $iq->where('name', 'like', "%{$query}%");
                    foreach ($keywords as $kw) {
                        $iq->orWhere('name', 'like', "%{$kw}%");
                    }
                });
            })
            ->orderByDesc('open')
            ->orderByDesc('orders_count')
            ->orderBy('min_delivery_time')
            ->limit($limit)
            ->get();
    }

    /** Format a km distance the way the storefront does: cap huge values at "1k+ km". */
    private function formatDistance(float $km): string
    {
        return $km >= 1000 ? '1k+ km away' : number_format($km, 1) . ' km away';
    }

    private function isGenericIntent(array $keywords): bool
    {
        if (empty($keywords)) {
            return true;
        }
        $allowed = array_merge(
            self::INTENT_TOP, self::INTENT_NEAR, self::INTENT_FAST,
            self::INTENT_POPULAR, self::INTENT_TOP_RATED,
            ['delivery'] // qualifier word that often rides along with "fast"/"free"
        );
        foreach ($keywords as $kw) {
            if (! in_array(strtolower($kw), $allowed, true)) {
                return false;
            }
        }
        return true;
    }

    /** True when at least one keyword is in the given intent vocabulary. */
    private function matchesIntent(array $keywords, array $vocab): bool
    {
        foreach ($keywords as $kw) {
            if (in_array(strtolower($kw), $vocab, true)) {
                return true;
            }
        }
        return false;
    }

    private function format(Store $store): array
    {
        // Store::getRatingAttribute() returns [r5, r4, r3, r2, r1] — feed it
        // straight into StoreLogic to get a real average; casting it to float
        // here used to produce 1.0 for every store.
        $avg     = 0.0;
        $buckets = $store->getAttribute('rating');
        if (is_array($buckets) && count($buckets) === 5) {
            $avg = (float) (StoreLogic::calculate_store_rating($buckets)['rating'] ?? 0);
        }

        // `open` comes from WithOpenWithDeliveryTime — falls back to the
        // toggle column `active` when the scope wasn't applied.
        $isOpen = $store->getAttribute('open') !== null
            ? (bool) $store->getAttribute('open')
            : (bool) $store->getAttribute('active');

        return [
            'id'            => $store->getKey(),
            'module_id'     => (int) $store->getAttribute('module_id'),
            'name'          => $store->getAttribute('name'),
            'logo'          => $store->getAttribute('logo'),
            'logo_full_url' => $store->logo_full_url,
            'cover_photo'   => $store->getAttribute('cover_photo'),
            'cover_photo_full_url' => $store->cover_photo_full_url,
            'avg_rating'    => $avg,
            'rating_count'  => (int) $store->getAttribute('rating_count'),
            'order_count'   => (int) ($store->getAttribute('orders_count') ?? $store->getAttribute('order_count') ?? 0),
            'items_count'   => (int) ($store->getAttribute('items_count') ?? 0),
            'reviews_count' => (int) ($store->getAttribute('reviews_count') ?? 0),
            'delivery_time' => $store->getAttribute('delivery_time'),
            'min_delivery_time' => (int) ($store->getAttribute('min_delivery_time') ?? 0),
            'distance_m'    => $store->getAttribute('distance') !== null ? (float) $store->getAttribute('distance') : null,
            'distance_km'   => $store->getAttribute('distance') !== null ? round(((float) $store->getAttribute('distance')) / 1000, 2) : null,
            'minimum_order' => (float) $store->getAttribute('minimum_order'),
            'free_delivery' => (bool) $store->getAttribute('free_delivery'),
            'is_open'       => $isOpen,
            'featured'      => (bool) $store->getAttribute('featured'),
            'delivery'      => (bool) $store->getAttribute('delivery'),
            'take_away'     => (bool) $store->getAttribute('take_away'),
            'veg'           => (bool) $store->getAttribute('veg'),
            'non_veg'       => (bool) $store->getAttribute('non_veg'),
            'discount'      => $store->discount ? [
                'min_purchase' => (float) ($store->discount->min_purchase ?? 0),
                'max_discount' => (float) ($store->discount->max_discount ?? 0),
                'discount'     => (float) ($store->discount->discount ?? 0),
                'discount_type'=> $store->discount->discount_type ?? null,
            ] : null,
        ];
    }
}
