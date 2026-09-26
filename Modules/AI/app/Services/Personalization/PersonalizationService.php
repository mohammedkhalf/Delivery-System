<?php

namespace Modules\AI\app\Services\Personalization;

use App\Models\Category;
use App\Models\Item;
use Modules\AI\app\Models\CustomerPreference;
use Modules\AI\app\Models\CustomerPreferenceSummary;
use Modules\AI\app\Jobs\ComputeUserPreferencesJob;
use Modules\AI\app\Core\Engines\OpenAIEngine;
use Illuminate\Support\Facades\DB;

class PersonalizationService
{
    const WEIGHTS = [
        'order'          => 10,
        'review'         => 8,
        'cart'           => 7,
        'item_wishlist'  => 5,
        'store_wishlist' => 5,
        'item_search'    => 4,
        'item_view'      => 2,
        'store_view'     => 2,
    ];

    const REBUILD_THRESHOLD = 5;

    /**
     * Record an item-based action (view, wishlist, cart, order).
     */
    public static function recordItemAction(int $userId, int $itemId, string $signal): void
    {
        if (!self::userExists($userId)) return;

        $item = DB::table('items')
            ->where('id', $itemId)
            ->select('category_id', 'store_id', 'module_id')
            ->first();

        if (!$item) return;

        $weight = self::WEIGHTS[$signal] ?? 0;
        if ($weight <= 0) return;

        self::upsertScore($userId, 'item', $itemId, $item->module_id, $weight);

        if ($item->category_id) {
            self::upsertScore($userId, 'category', $item->category_id, $item->module_id, $weight);
        }

        if ($item->store_id) {
            self::upsertScore($userId, 'store', $item->store_id, $item->module_id, $weight);
        }

        self::markSummaryDirty($userId, $item->module_id);
    }

    /**
     * Record a service action. Service = the module's "item" (stored as
     * preference_type 'item'); provider = store; category = shared categories.
     */
    public static function recordServiceAction(int $userId, int $serviceId, string $signal): void
    {
        if (!self::userExists($userId)) return;

        $service = DB::table('services')
            ->where('id', $serviceId)
            ->select('category_id', 'sub_category_id', 'store_id', 'module_id')
            ->first();

        if (!$service) return;

        $weight = self::WEIGHTS[$signal] ?? 0;
        if ($weight <= 0) return;

        self::upsertScore($userId, 'item', $serviceId, $service->module_id, $weight);

        if ($service->category_id) {
            self::upsertScore($userId, 'category', $service->category_id, $service->module_id, $weight);
        }

        if ($service->sub_category_id) {
            self::upsertScore($userId, 'category', $service->sub_category_id, $service->module_id, $weight);
        }

        if ($service->store_id) {
            self::upsertScore($userId, 'store', $service->store_id, $service->module_id, $weight);
        }

        self::markSummaryDirty($userId, $service->module_id);
    }

    /**
     * Record a rental vehicle action. Vehicle = "item"; provider = store (via
     * provider_id); category = vehicle_categories. Vehicles have no module_id
     * column, so the rental module id is passed in.
     */
    public static function recordVehicleAction(int $userId, int $vehicleId, string $signal, ?int $moduleId): void
    {
        if (!self::userExists($userId)) return;
        if (!$moduleId) return;

        $vehicle = DB::table('vehicles')
            ->where('id', $vehicleId)
            ->select('category_id', 'provider_id')
            ->first();

        if (!$vehicle) return;

        $weight = self::WEIGHTS[$signal] ?? 0;
        if ($weight <= 0) return;

        self::upsertScore($userId, 'item', $vehicleId, $moduleId, $weight);

        if ($vehicle->category_id) {
            self::upsertScore($userId, 'category', $vehicle->category_id, $moduleId, $weight);
        }

        if ($vehicle->provider_id) {
            self::upsertScore($userId, 'store', $vehicle->provider_id, $moduleId, $weight);
        }

        self::markSummaryDirty($userId, $moduleId);
    }

    /**
     * Record a store-based action (view, wishlist).
     */
    public static function recordStoreAction(int $userId, int $storeId, string $signal): void
    {
        if (!self::userExists($userId)) return;

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->select('module_id')
            ->first();

        if (!$store) return;

        $weight = self::WEIGHTS[$signal] ?? 0;
        if ($weight <= 0) return;

        self::upsertScore($userId, 'store', $storeId, $store->module_id, $weight);
        self::markSummaryDirty($userId, $store->module_id);
    }

    /**
     * Record a search action — maps keyword to categories.
     */
    public static function recordSearchAction(int $userId, string $keyword, ?int $moduleId): void
    {
        if (!self::userExists($userId)) return;

        if (!$keyword) return;

        $weight = self::WEIGHTS['item_search'];

        $categories = Category::where('name', 'LIKE', "%{$keyword}%")
            ->select('id', 'module_id')
            ->limit(5)
            ->get();

        $dirty = false;
        foreach ($categories as $cat) {
            $catModuleId = $cat->module_id ?? $moduleId;
            self::upsertScore($userId, 'category', $cat->id, $catModuleId, $weight);
            $dirty = true;
        }

        if ($dirty) {
            self::markSummaryDirty($userId, $moduleId);
        }
    }

    /**
     * Skip recording when the user row no longer exists. Guards against
     * customer_preferences.user_id FK violations from stale Passport tokens
     * whose user was hard-deleted.
     */
    private static function userExists(int $userId): bool
    {
        return $userId > 0 && DB::table('users')->where('id', $userId)->exists();
    }

    /**
     * Increment score for a single preference row.
     */
    private static function upsertScore(int $userId, string $type, int $referenceId, ?int $moduleId, float $weight): void
    {
        DB::table('customer_preferences')->updateOrInsert(
            [
                'user_id' => $userId,
                'preference_type' => $type,
                'reference_id' => $referenceId,
                'module_id' => $moduleId,
            ],
            [
                'score' => DB::raw("COALESCE(score, 0) + {$weight}"),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    /**
     * Mark the user's summary as dirty. Dispatch rebuild job if threshold hit.
     */
    public static function markSummaryDirty(int $userId, ?int $moduleId): void
    {
        $summary = CustomerPreferenceSummary::firstOrCreate(
            ['user_id' => $userId, 'module_id' => $moduleId],
            ['update_count' => 0]
        );

        $summary->increment('update_count');

        if ($summary->update_count >= self::REBUILD_THRESHOLD) {
            ComputeUserPreferencesJob::dispatch($userId, $moduleId);
        }
    }

    /**
     * Rebuild the summary table for a user+module.
     */
    public static function rebuildSummary(int $userId, ?int $moduleId): void
    {
        // Which table keyword/AI resolution reads (columns are shared).
        $mt = self::moduleType($moduleId);
        $primaryTable = $mt === 'service' ? 'service' : ($mt === 'rental' ? 'vehicle' : 'item');

        $topItems = CustomerPreference::where('user_id', $userId)
            ->where('preference_type', 'item')
            ->where('module_id', $moduleId)
            ->orderByDesc('score')
            ->limit(20)
            ->pluck('reference_id')
            ->toArray();

        $topCategories = CustomerPreference::where('user_id', $userId)
            ->where('preference_type', 'category')
            ->where('module_id', $moduleId)
            ->orderByDesc('score')
            ->limit(20)
            ->pluck('reference_id')
            ->toArray();

        $topStores = CustomerPreference::where('user_id', $userId)
            ->where('preference_type', 'store')
            ->where('module_id', $moduleId)
            ->orderByDesc('score')
            ->limit(20)
            ->pluck('reference_id')
            ->toArray();


        if (\Modules\AI\app\Core\AiModule::isOpenAiConfigured()) {
            $aiKeywords = self::getAiKeywords($userId, $topItems, $topCategories, $topStores, $moduleId, $primaryTable);
        } else {
            $aiKeywords = [];
        }

        // Resolve keywords to actual IDs (heavy queries run here in job, not at API time)
        $resolvedIds = self::resolveKeywordsToIds($aiKeywords, $topItems, $topCategories, $topStores, $moduleId, $primaryTable);

        CustomerPreferenceSummary::updateOrCreate(
            ['user_id' => $userId, 'module_id' => $moduleId],
            [
                'top_items' => $topItems,
                'top_categories' => $topCategories,
                'top_stores' => $topStores,
                'ai_keywords' => $aiKeywords,
                'keyword_item_ids' => $resolvedIds['primary'],
                'keyword_category_ids' => $resolvedIds['categories'],
                'keyword_store_ids' => $resolvedIds['stores'],
                'update_count' => 0,
                'last_rebuilt_at' => now(),
            ]
        );
    }

    /**
     * Resolve a module id to its module_type string ('food','service',...).
     */
    private static function moduleType(?int $moduleId): string
    {
        if (!$moduleId) return 'general';
        $module = DB::table('modules')->where('id', $moduleId)->first();
        return $module->module_type ?? 'general';
    }

    /**
     * Resolve AI keywords to actual item/category/store IDs.
     * This runs ONCE in the queued job — heavy LIKE + JOIN queries happen here, not at API time.
     */
    private static function resolveKeywordsToIds(array $keywords, array $excludeItemIds, array $excludeCategoryIds, array $excludeStoreIds, ?int $moduleId, string $primaryType = 'item'): array
    {
        $itemIds = [];
        $categoryIds = [];
        $storeIds = [];

        if (empty($keywords)) {
            return ['primary' => [], 'categories' => [], 'stores' => []];
        }

        foreach ($keywords as $keyword) {
            if (!is_string($keyword) || empty(trim($keyword))) continue;
            $kw = trim($keyword);

            // Primary entity by name + tags (table varies by module).
            if ($primaryType === 'service') {
                $matchedItems = DB::table('services')
                    ->where('module_id', $moduleId)
                    ->where('status', 1)
                    ->where(function ($q) use ($kw) {
                        $q->where('name', 'LIKE', "%{$kw}%")
                          ->orWhere('tags', 'LIKE', "%{$kw}%");
                    })
                    ->whereNotIn('id', $excludeItemIds)
                    ->whereNotIn('id', $itemIds)
                    ->limit(5)
                    ->pluck('id')
                    ->toArray();
            } elseif ($primaryType === 'vehicle') {
                $matchedItems = DB::table('vehicles')
                    ->where('status', 1)
                    ->where(function ($q) use ($kw) {
                        $q->where('name', 'LIKE', "%{$kw}%")
                          ->orWhere('tag', 'LIKE', "%{$kw}%");
                    })
                    ->whereNotIn('id', $excludeItemIds)
                    ->whereNotIn('id', $itemIds)
                    ->limit(5)
                    ->pluck('id')
                    ->toArray();
            } else {
                $matchedItems = Item::where('module_id', $moduleId)
                    ->where('status', 1)
                    ->where(function ($q) use ($kw) {
                        $q->where('name', 'LIKE', "%{$kw}%")
                          ->orWhereHas('tags', fn($t) => $t->where('tag', 'LIKE', "%{$kw}%"))
                          ->orWhereHas('translations', fn($t) => $t->where('key', 'name')->where('value', 'LIKE', "%{$kw}%"));
                    })
                    ->whereNotIn('id', $excludeItemIds)
                    ->whereNotIn('id', $itemIds)
                    ->limit(5)
                    ->pluck('id')
                    ->toArray();
            }

            $itemIds = array_merge($itemIds, $matchedItems);

            // Categories: rental → flat vehicle_categories; else shared categories.
            if ($primaryType === 'vehicle') {
                $matchedCategories = DB::table('vehicle_categories')
                    ->where('status', 1)
                    ->where('name', 'LIKE', "%{$kw}%")
                    ->whereNotIn('id', $excludeCategoryIds)
                    ->whereNotIn('id', $categoryIds)
                    ->limit(3)
                    ->pluck('id')
                    ->toArray();
            } else {
                $matchedCategories = Category::where('status', 1)
                    ->where(function ($q) use ($kw) {
                        $q->where('name', 'LIKE', "%{$kw}%")
                          ->orWhereHas('translations', fn($t) => $t->where('key', 'name')->where('value', 'LIKE', "%{$kw}%"));
                    })
                    ->whereNotIn('id', $excludeCategoryIds)
                    ->whereNotIn('id', $categoryIds)
                    ->limit(3)
                    ->pluck('id')
                    ->toArray();
            }

            $categoryIds = array_merge($categoryIds, $matchedCategories);

            // Match stores by: name, address, translations
            $matchedStores = DB::table('stores')
                ->where('module_id', $moduleId)
                ->where('status', 1)
                ->where(function ($q) use ($kw) {
                    $q->where('name', 'LIKE', "%{$kw}%")
                      ->orWhere('address', 'LIKE', "%{$kw}%")
                      ->orWhereExists(function ($sub) use ($kw) {
                          $sub->select(DB::raw(1))
                              ->from('translations')
                              ->whereColumn('translations.translationable_id', 'stores.id')
                              ->where('translations.translationable_type', 'App\\Models\\Store')
                              ->where('translations.key', 'name')
                              ->where('translations.value', 'LIKE', "%{$kw}%");
                      });
                })
                ->whereNotIn('id', $excludeStoreIds)
                ->whereNotIn('id', $storeIds)
                ->limit(3)
                ->pluck('id')
                ->toArray();

            $storeIds = array_merge($storeIds, $matchedStores);
        }

        return [
            'primary' => array_slice(array_unique($itemIds), 0, 30),
            'categories' => array_slice(array_unique($categoryIds), 0, 15),
            'stores' => array_slice(array_unique($storeIds), 0, 15),
        ];
    }

    /**
     * Build the "top products" context block for a Service-module user from
     * the `services` table (analogous to the item context in getAiKeywords).
     */
    private static function buildServiceContext(array $topServiceIds): string
    {
        $services = DB::table('services')
            ->leftJoin('categories', 'services.category_id', '=', 'categories.id')
            ->whereIn('services.id', array_slice($topServiceIds, 0, 15))
            ->select('services.name', 'services.base_price', 'services.avg_rating', 'services.tags', 'categories.name as category_name')
            ->get();

        if ($services->isEmpty()) return '';

        $lines = $services->map(function ($s) {
            $cat = $s->category_name ?? 'Unknown';
            $rating = $s->avg_rating ? round($s->avg_rating, 1) : 'N/A';
            $tags = '';
            if ($s->tags) {
                $decoded = json_decode($s->tags, true);
                if (is_array($decoded)) $tags = implode(', ', array_filter($decoded, 'is_string'));
            }
            $line = "- {$s->name} | Price: {$s->base_price} | Category: {$cat} | Rating: {$rating}/5";
            if ($tags) $line .= " | Tags: {$tags}";
            return $line;
        })->implode("\n");

        $prices = $services->pluck('base_price')->filter(fn($p) => $p !== null && $p > 0);
        $avgPrice = $prices->isNotEmpty() ? round($prices->avg(), 2) : 0;
        $minPrice = $prices->min() ?? 0;
        $maxPrice = $prices->max() ?? 0;

        return "CUSTOMER'S TOP SERVICES (by interaction score):\n{$lines}\nPrice range: {$minPrice} - {$maxPrice} (Avg: {$avgPrice})";
    }

    /**
     * Build the "top products" context block for a Rental-module user from the
     * `vehicles` table (category via the dedicated `vehicle_categories`).
     */
    private static function buildVehicleContext(array $topVehicleIds): string
    {
        $vehicles = DB::table('vehicles')
            ->leftJoin('vehicle_categories', 'vehicles.category_id', '=', 'vehicle_categories.id')
            ->leftJoin('vehicle_brands', 'vehicles.brand_id', '=', 'vehicle_brands.id')
            ->whereIn('vehicles.id', array_slice($topVehicleIds, 0, 15))
            ->select('vehicles.name', 'vehicles.hourly_price', 'vehicles.distance_price', 'vehicles.avg_rating',
                     'vehicles.type', 'vehicles.fuel_type', 'vehicle_categories.name as category_name', 'vehicle_brands.name as brand_name')
            ->get();

        if ($vehicles->isEmpty()) return '';

        $lines = $vehicles->map(function ($v) {
            $cat = $v->category_name ?? 'Unknown';
            $rating = $v->avg_rating ? round($v->avg_rating, 1) : 'N/A';
            $price = ($v->hourly_price > 0 ? $v->hourly_price.'/hr' : '') . ($v->distance_price > 0 ? ' '.$v->distance_price.'/km' : '');
            $extra = collect([$v->brand_name, $v->type, $v->fuel_type])->filter()->implode(', ');
            $line = "- {$v->name} | Price: ".trim($price ?: 'N/A')." | Category: {$cat} | Rating: {$rating}/5";
            if ($extra) $line .= " | {$extra}";
            return $line;
        })->implode("\n");

        return "CUSTOMER'S TOP VEHICLES (by interaction score):\n{$lines}";
    }

    /**
     * Use OpenAI to analyze full user behavior and suggest search keywords.
     */
    public static function getAiKeywords(int $userId, array $topItemIds, array $topCategoryIds, array $topStoreIds, ?int $moduleId, string $primaryType = 'item'): array
    {
        if (empty($topItemIds) && empty($topCategoryIds)) return [];

        try {
            $user = DB::table('users')->where('id', $userId)->select('interest', 'user_context')->first();
            if (!$user) return [];

            $itemContext = '';
            if (!empty($topItemIds) && $primaryType === 'service') {
                $itemContext = self::buildServiceContext($topItemIds);
            }
            if (!empty($topItemIds) && $primaryType === 'vehicle') {
                $itemContext = self::buildVehicleContext($topItemIds);
            }
            if (!empty($topItemIds) && !in_array($primaryType, ['service', 'vehicle'], true)) {
                $items = Item::whereIn('id', array_slice($topItemIds, 0, 15))
                    ->with(['category:id,name', 'tags:id,tag'])
                    ->select('id', 'name', 'price', 'category_id', 'avg_rating', 'veg', 'organic', 'is_halal')
                    ->get();

                if ($items->isNotEmpty()) {
                    $itemLines = $items->map(function ($item) {
                        $cat = $item->category?->name ?? 'Unknown';
                        $tags = $item->tags->pluck('tag')->implode(', ');
                        $rating = $item->avg_rating ? round($item->avg_rating, 1) : 'N/A';
                        $flags = collect([
                            $item->veg ? 'Veg' : null,
                            $item->organic ? 'Organic' : null,
                            $item->is_halal ? 'Halal' : null,
                        ])->filter()->implode(', ');

                        $line = "- {$item->name} | Price: {$item->price} | Category: {$cat} | Rating: {$rating}/5";
                        if ($tags) $line .= " | Tags: {$tags}";
                        if ($flags) $line .= " | Flags: {$flags}";
                        return $line;
                    })->implode("\n");

                    $prices = $items->pluck('price')->filter();
                    $avgPrice = $prices->isNotEmpty() ? round($prices->avg(), 2) : 0;
                    $minPrice = $prices->min() ?? 0;
                    $maxPrice = $prices->max() ?? 0;

                    $itemContext = "CUSTOMER'S TOP PRODUCTS (by interaction score):\n{$itemLines}\nPrice range: {$minPrice} - {$maxPrice} (Avg: {$avgPrice})";
                }
            }

            $categoryContext = '';
            if (!empty($topCategoryIds)) {
                $categoryNames = $primaryType === 'vehicle'
                    ? DB::table('vehicle_categories')->whereIn('id', array_slice($topCategoryIds, 0, 10))->pluck('name')->toArray()
                    : Category::whereIn('id', array_slice($topCategoryIds, 0, 10))->pluck('name')->toArray();
                if (!empty($categoryNames)) {
                    $categoryContext = "\nTOP PREFERRED CATEGORIES (ranked): " . implode(', ', $categoryNames);
                }
            }

            $storeContext = '';
            if (!empty($topStoreIds)) {
                $stores = DB::table('stores')
                    ->whereIn('id', array_slice($topStoreIds, 0, 10))
                    ->select('name', 'rating')
                    ->get();
                if ($stores->isNotEmpty()) {
                    $storeLines = $stores->map(function ($s) {
                        $r = $s->rating ? json_decode($s->rating, true) : null;
                        $avgR = $r ? round(array_sum($r) / max(count($r), 1), 1) : 'N/A';
                        return "{$s->name} (Rating: {$avgR}/5)";
                    })->implode(', ');
                    $storeContext = "\nTOP PREFERRED STORES: {$storeLines}";
                }
            }

            $interestContext = '';
            if ($user->interest) {
                $interestIds = json_decode($user->interest, true);
                if (!empty($interestIds) && is_array($interestIds)) {
                    $interestNames = Category::whereIn('id', $interestIds)->pluck('name')->toArray();
                    if (!empty($interestNames)) {
                        $interestContext = "\nCUSTOMER'S SELF-SELECTED INTERESTS: " . implode(', ', $interestNames);
                    }
                }
            }

            $personaContext = '';
            if ($user->user_context) {
                $personaContext = "\nPREVIOUS PERSONA ASSESSMENT: {$user->user_context}";
            }

            $scoreContext = '';
            $scoreBreakdown = CustomerPreference::where('user_id', $userId)
                ->where('module_id', $moduleId)
                ->orderByDesc('score')
                ->limit(10)
                ->select('preference_type', 'reference_id', 'score')
                ->get();
            if ($scoreBreakdown->isNotEmpty()) {
                $lines = $scoreBreakdown->map(fn($p) => "{$p->preference_type}#{$p->reference_id}: {$p->score} pts");
                $scoreContext = "\nPREFERENCE SCORES (higher = stronger signal): " . $lines->implode(', ');
            }

            $moduleType = 'general';
            if ($moduleId) {
                $module = DB::table('modules')->where('id', $moduleId)->first();
                $moduleType = $module->module_type ?? 'general';
            }

            // Build module-specific instruction block
            $moduleInstructions = match($moduleType) {
                'food' => "MODULE: Food Delivery
DOMAIN RULES:
- Pair by meal logic: if main dish → suggest sides, drinks, desserts, sauces
- Respect cuisine patterns: if user orders Italian → suggest within Italian + adjacent cuisines (Mediterranean, Spanish)
- Time-aware pairing: breakfast items → coffee, juice; dinner items → appetizers, wine
- Dietary consistency: veg user → ONLY suggest veg; halal user → ONLY suggest halal
- Store type: restaurant names, cuisine-specific eateries, cloud kitchens",

                'grocery' => "MODULE: Grocery & Daily Essentials
DOMAIN RULES:
- Pair by basket logic: rice → oil, dal, spices; bread → butter, jam, eggs; milk → cereal, coffee, tea
- Seasonal awareness: suggest items commonly bought together in weekly/monthly shopping
- Brand affinity: if user buys organic brands → suggest other organic alternatives
- Pantry completion: identify gaps in typical household shopping patterns
- Store type: supermarkets, organic stores, wholesale, local grocery",

                'pharmacy' => "MODULE: Pharmacy & Health
DOMAIN RULES:
- Pair by health need: vitamins → related supplements, health drinks; pain relief → muscle balm, hot packs
- Wellness patterns: if buying fitness supplements → suggest protein bars, shakers, gym accessories
- Preventive care: if buying cold medicine → suggest immunity boosters, vitamin C, honey
- Never suggest conflicting medications — stick to complementary wellness products
- Store type: pharmacy chains, health stores, wellness centers",

                'ecommerce', 'shop' => "MODULE: E-commerce & Shopping
DOMAIN RULES:
- Pair by compatibility: phone → case, charger, screen protector; laptop → bag, mouse, keyboard
- Style matching: if fashion items → suggest matching accessories, similar style items
- Brand ecosystem: if Apple product → suggest Apple accessories; if Nike → suggest Nike gear
- Price tier consistency: budget buyer → budget alternatives; premium → premium suggestions
- Store type: electronics stores, fashion outlets, brand stores, general retail",

                'service' => "MODULE: On-Demand Services
DOMAIN RULES:
- Pair by service journey: cleaning → pest control, sanitization; AC repair → AC installation, gas refill; salon → spa, grooming; plumbing → electrical, carpentry
- Respect the customer's category history and the provider specialties they book
- Match the service tier / price band the customer books (budget / standard / premium)
- Suggest complementary and recurring follow-up services commonly booked together
- Provider type: specialist pros, multi-service agencies, verified/rated providers",

                'rental' => "MODULE: Vehicle Rental
DOMAIN RULES:
- Keywords must match vehicle NAMES / models / types (e.g. \"sedan\", \"suv\", \"scooter\", \"hatchback\", \"minivan\", \"luxury\", \"electric\")
- Respect the customer's vehicle category + brand history and their booked price band (hourly / per-km)
- Match seating and use-case patterns (family SUV vs solo scooter vs group van)
- Provider type: rental agencies, fleet operators, verified providers the customer rents from
- category_keywords should map to vehicle categories (car / bike / suv / luxury / commercial)",

                default => "MODULE: General Marketplace
DOMAIN RULES:
- Pair by logical association: suggest items commonly purchased together
- Match the price tier of existing purchases
- Suggest variety within established preferences
- Store type: based on the types of stores the user already visits",
            };

            $prompt = "You are a personalization engine for a multi-vendor marketplace. Your output directly controls what products, stores, and categories a customer sees first. Accuracy matters — bad keywords waste the customer's attention.

CUSTOMER DATA:
{$itemContext}
{$categoryContext}
{$storeContext}
{$interestContext}
{$personaContext}
{$scoreContext}

{$moduleInstructions}

TASK 1 — PERSONA (2-3 sentences, be specific not generic):
Analyze the data and describe THIS customer. Include:
- Spending tier: budget (lowest prices) / value (mid-range) / premium (highest prices) — based on ACTUAL price data above
- Core preferences: what specific types of products they consistently choose
- Behavioral pattern: loyal to few stores or exploring many? bulk buyer or frequent small orders?
- Any dietary/lifestyle signals from product flags and tags
Do NOT write generic descriptions. Use the actual product names and categories from the data.

TASK 2 — KEYWORDS (these will be searched against our product database):

product_keywords (12-15): Single words or 2-word phrases that would appear in PRODUCT NAMES the customer would want next.
Think: what would this customer type into a search bar? What complementary products pair with what they already buy?
GOOD: \"yogurt\", \"granola\", \"almond milk\", \"protein bar\"
BAD: \"healthy food\", \"good stuff\", \"recommended\" (too vague, won't match real product names)

store_keywords (5-8): Words that would appear in STORE NAMES this customer would like.
Think: store types, cuisine names, brand names, specialty descriptors.
GOOD: \"organic\", \"bakery\", \"pizza\", \"fresh\", \"grill\"
BAD: \"good restaurant\", \"nice shop\" (too vague)

category_keywords (5-8): Words that would appear in CATEGORY NAMES adjacent to current preferences.
Think: what category sections should we highlight for this customer?
GOOD: \"dairy\", \"snacks\", \"beverages\", \"breakfast\", \"frozen\"
BAD: \"food items\", \"products\" (too generic)

CRITICAL RULES:
- Keywords must match real product/store/category names in a database — be practical, not aspirational
- DO NOT repeat products, stores, or categories the customer already has
- 1-2 words per keyword, lowercase
- Respect dietary constraints absolutely (veg/halal/organic user = only matching keywords)
- Match the customer's price tier

Return ONLY this JSON, nothing else:
{\"persona\": \"...\", \"product_keywords\": [...], \"store_keywords\": [...], \"category_keywords\": [...]}";

            $engine = new OpenAIEngine();
            $response = $engine->core($prompt);

            $response = preg_replace('/```json\s*/', '', $response);
            $response = preg_replace('/```\s*/', '', $response);
            $response = trim($response);

            $parsed = json_decode($response, true);
            if (!is_array($parsed)) return [];

            if (!empty($parsed['persona']) && is_string($parsed['persona'])) {
                DB::table('users')->where('id', $userId)->update([
                    'user_context' => $parsed['persona'],
                ]);
            }

            $allKeywords = [];
            foreach (['product_keywords', 'store_keywords', 'category_keywords'] as $type) {
                $kws = $parsed[$type] ?? [];
                if (is_array($kws)) {
                    foreach ($kws as $kw) {
                        if (is_string($kw) && !empty(trim($kw))) {
                            $allKeywords[] = trim($kw);
                        }
                    }
                }
            }

            return array_slice(array_unique($allKeywords), 0, 25);

        } catch (\Throwable $e) {
            info('Personalization AI keyword generation failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply personalization to item listing queries. Reused across modules —
     * only the table and provider column vary (see $map).
     */
    public static function applyItemPersonalization($query, ?int $userId, $filter = null)
    {
        if (!$userId) return $query;

        $moduleData = config('module.current_module_data');
        $moduleId = is_array($moduleData) ? ($moduleData['id'] ?? null) : null;
        if (!$moduleId) return $query;

        $moduleType = (is_array($moduleData) ? ($moduleData['module_type'] ?? null) : null)
            ?? config('module.current_module_type');
        $map = [
            'service' => ['table' => 'services', 'store' => 'store_id'],
            'rental'  => ['table' => 'vehicles', 'store' => 'provider_id'],
        ];
        $cfg = $map[$moduleType] ?? ['table' => 'items', 'store' => 'store_id'];
        $table = $cfg['table'];
        $storeCol = $cfg['store'];

        $summary = CustomerPreferenceSummary::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->first();

        if (!$summary) return $query;

        $itemIds = $summary->top_items ?? [];
        $categoryIds = $summary->top_categories ?? [];
        $storeIds = $summary->top_stores ?? [];
        // Pre-resolved keyword IDs (resolved during job, not here)
        $kwItemIds = $summary->keyword_item_ids ?? [];
        $kwCategoryIds = $summary->keyword_category_ids ?? [];
        $kwStoreIds = $summary->keyword_store_ids ?? [];

        // Merge direct preferences with keyword-resolved IDs
        $allItemIds = array_unique(array_merge($itemIds, $kwItemIds));
        $allCategoryIds = array_unique(array_merge($categoryIds, $kwCategoryIds));
        $allStoreIds = array_unique(array_merge($storeIds, $kwStoreIds));

        if (empty($allItemIds) && empty($allCategoryIds) && empty($allStoreIds)) {
            return $query;
        }

        $scoreParts = [];

        // Item match: direct preference items get 50pts (ranked), keyword items get 20pts
        if (!empty($allItemIds)) {
            $cases = [];
            foreach (array_slice($itemIds, 0, 20) as $i => $id) {
                $score = 50 - ($i * 2);
                $cases[] = "WHEN {$table}.id = " . intval($id) . " THEN {$score}";
            }
            foreach (array_slice($kwItemIds, 0, 30) as $id) {
                if (!in_array($id, $itemIds)) {
                    $cases[] = "WHEN {$table}.id = " . intval($id) . " THEN 20";
                }
            }
            $scoreParts[] = "(CASE " . implode(' ', $cases) . " ELSE 0 END)";
        }

        // Category match: direct 30pts (ranked), keyword-resolved 15pts
        if (!empty($allCategoryIds)) {
            $cases = [];
            foreach (array_slice($categoryIds, 0, 20) as $i => $id) {
                $score = 30 - ($i * 1);
                $cases[] = "WHEN {$table}.category_id = " . intval($id) . " THEN {$score}";
            }
            foreach (array_slice($kwCategoryIds, 0, 15) as $id) {
                if (!in_array($id, $categoryIds)) {
                    $cases[] = "WHEN {$table}.category_id = " . intval($id) . " THEN 15";
                }
            }
            $scoreParts[] = "(CASE " . implode(' ', $cases) . " ELSE 0 END)";
        }

        // Store match: direct 15pts (ranked), keyword-resolved 8pts
        if (!empty($allStoreIds)) {
            $cases = [];
            foreach (array_slice($storeIds, 0, 20) as $i => $id) {
                $score = 15 - ($i * 0.5);
                $cases[] = "WHEN {$table}.{$storeCol} = " . intval($id) . " THEN {$score}";
            }
            foreach (array_slice($kwStoreIds, 0, 15) as $id) {
                if (!in_array($id, $storeIds)) {
                    $cases[] = "WHEN {$table}.{$storeCol} = " . intval($id) . " THEN 8";
                }
            }
            $scoreParts[] = "(CASE " . implode(' ', $cases) . " ELSE 0 END)";
        }

        $scoreExpr = implode(' + ', $scoreParts);
        $query = $query->orderByRaw("({$scoreExpr}) DESC");

        return $query;
    }

    /**
     * Apply personalization to store listing queries.
     */
    public static function applyStorePersonalization($query, ?int $userId, $filter = null)
    {
        if (!$userId) return $query;

        $moduleId = config('module.current_module_data') ? config('module.current_module_data')['id'] : null;
        if (!$moduleId) return $query;

        $storeIds = [];
        $kwStoreIds = [];

        $summary = CustomerPreferenceSummary::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->first();

        if ($summary) {
            $storeIds = $summary->top_stores ?? [];
            $kwStoreIds = $summary->keyword_store_ids ?? [];
        }

        // Fallback: no aggregated summary yet → read raw preferences so the
        // user's first store-view / wishlist / order influences the ranking
        // without waiting for the queued ComputeUserPreferencesJob.
        if (empty($storeIds) && empty($kwStoreIds)) {
            $storeIds = CustomerPreference::where('user_id', $userId)
                ->where('preference_type', 'store')
                ->where('module_id', $moduleId)
                ->orderByDesc('score')
                ->limit(20)
                ->pluck('reference_id')
                ->toArray();
        }

        $allStoreIds = array_unique(array_merge($storeIds, $kwStoreIds));
        if (empty($allStoreIds)) return $query;

        $cases = [];
        // Direct preference stores: 30pts ranked
        foreach (array_slice($storeIds, 0, 20) as $i => $id) {
            $score = 30 - ($i * 1);
            $cases[] = "WHEN stores.id = " . intval($id) . " THEN {$score}";
        }
        // Keyword-resolved stores: 12pts
        foreach (array_slice($kwStoreIds, 0, 15) as $id) {
            if (!in_array($id, $storeIds)) {
                $cases[] = "WHEN stores.id = " . intval($id) . " THEN 12";
            }
        }
        if (empty($cases)) return $query;

        $scoreExpr = "(CASE " . implode(' ', $cases) . " ELSE 0 END)";
        $query = $query->orderByRaw("{$scoreExpr} DESC");

        return $query;
    }


    /**
     * Apply personalization to category listing queries.
     *
     * Prefers the pre-aggregated summary (built by ComputeUserPreferencesJob
     * after the user accumulates REBUILD_THRESHOLD actions). When that
     * summary doesn't exist yet — or has no category data — we fall back to
     * reading the raw customer_preferences table directly so a user's very
     * first search/view already lifts the relevant category to the top of
     * the list, without waiting for the queue / threshold.
     */
    public static function applyCategoryPersonalization($query, ?int $userId)
    {
        if (!$userId) return $query;

        $moduleData = config('module.current_module_data');
        $moduleId = is_array($moduleData) ? ($moduleData['id'] ?? null) : null;
        if (!$moduleId) return $query;

        $moduleType = (is_array($moduleData) ? ($moduleData['module_type'] ?? null) : null)
            ?? config('module.current_module_type');

        $categoryIds = [];
        $kwCategoryIds = [];

        $summary = CustomerPreferenceSummary::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->first();

        if ($summary) {
            $categoryIds   = $summary->top_categories ?? [];
            $kwCategoryIds = $summary->keyword_category_ids ?? [];
        }

        // Fallback: nothing aggregated yet → read raw preferences directly so
        // recent activity still influences the order on the very next request.
        if (empty($categoryIds) && empty($kwCategoryIds)) {
            $categoryIds = CustomerPreference::where('user_id', $userId)
                ->where('preference_type', 'category')
                ->where('module_id', $moduleId)
                ->orderByDesc('score')
                ->limit(20)
                ->pluck('reference_id')
                ->toArray();
        }

        $allCategoryIds = array_unique(array_merge($categoryIds, $kwCategoryIds));
        if (empty($allCategoryIds)) return $query;

        // Rental: flat vehicle_categories (no parent), rank ids directly.
        if ($moduleType === 'rental') {
            $ranked = [];
            foreach (array_merge(array_slice($categoryIds, 0, 20), array_slice($kwCategoryIds, 0, 15)) as $id) {
                $id = (int) $id;
                if ($id > 0 && !in_array($id, $ranked, true)) {
                    $ranked[] = $id;
                }
            }
            $cases = [];
            foreach ($ranked as $i => $id) {
                $score = 30 - $i;
                if ($score < 1) break;
                $cases[] = "WHEN vehicle_categories.id = " . intval($id) . " THEN {$score}";
            }
            if (empty($cases)) return $query;
            return $query->orderByRaw("(CASE " . implode(' ', $cases) . " ELSE 0 END) DESC");
        }

        // The category-listing endpoint filters `position = 0` (parents
        // only), but item/keyword preferences usually accumulate against the
        // SUBCATEGORY the item belongs to (position = 1). Resolve every
        // preferred sub-category up to its top-level parent so the parent
        // gets credit for the user's interest in its children. The resulting
        // ranked list contains BOTH original preferences (sub-cat scores get
        // ignored in the parent-only listing anyway) and the surfaced
        // top-level parents.
        $idsToResolve = array_unique(array_merge(
            array_slice($categoryIds, 0, 20),
            array_slice($kwCategoryIds, 0, 15)
        ));

        $idToParent = Category::whereIn('id', $idsToResolve)
            ->pluck('parent_id', 'id')
            ->toArray();

        // Ranked array of top-level category IDs, ordered by their best
        // contributing preference rank. Earlier entries = stronger interest.
        $rankedParents = [];
        foreach (array_slice($categoryIds, 0, 20) as $id) {
            $parentId = !empty($idToParent[$id]) ? (int) $idToParent[$id] : (int) $id;
            if (!in_array($parentId, $rankedParents, true)) {
                $rankedParents[] = $parentId;
            }
        }
        foreach (array_slice($kwCategoryIds, 0, 15) as $id) {
            $parentId = !empty($idToParent[$id]) ? (int) $idToParent[$id] : (int) $id;
            if (!in_array($parentId, $rankedParents, true)) {
                $rankedParents[] = $parentId;
            }
        }

        $cases = [];
        foreach ($rankedParents as $i => $parentId) {
            $score = 30 - $i;
            if ($score < 1) break;
            $cases[] = "WHEN categories.id = " . intval($parentId) . " THEN {$score}";
        }
        if (empty($cases)) return $query;

        $scoreExpr = "(CASE " . implode(' ', $cases) . " ELSE 0 END)";
        $query = $query->orderByRaw("{$scoreExpr} DESC");

        return $query;
    }

    /**
     * Apply personalization to item campaign queries.
     */
    public static function applyCampaignPersonalization($query, ?int $userId)
    {
        if (!$userId) return $query;

        $moduleId = config('module.current_module_data') ? config('module.current_module_data')['id'] : null;
        if (!$moduleId) return $query;

        $summary = CustomerPreferenceSummary::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->first();

        if (!$summary) return $query;

        $categoryIds = $summary->top_categories ?? [];
        $kwCategoryIds = $summary->keyword_category_ids ?? [];
        $allCategoryIds = array_unique(array_merge($categoryIds, $kwCategoryIds));

        if (empty($allCategoryIds)) return $query;

        $cases = [];
        // Direct preference categories: 30pts ranked
        foreach (array_slice($categoryIds, 0, 20) as $i => $id) {
            $score = 30 - ($i * 1);
            $cases[] = "WHEN item_campaigns.category_id = " . intval($id) . " THEN {$score}";
        }
        // Keyword-resolved categories: 15pts
        foreach (array_slice($kwCategoryIds, 0, 15) as $id) {
            if (!in_array($id, $categoryIds)) {
                $cases[] = "WHEN item_campaigns.category_id = " . intval($id) . " THEN 15";
            }
        }

        $scoreExpr = "(CASE " . implode(' ', $cases) . " ELSE 0 END)";
        $query = $query->orderByRaw("{$scoreExpr} DESC");

        return $query;
    }

    /**
     * Reorder a collection by user preferences (post-query).
     */
    public static function reorderByPreference($collection, ?int $userId, string $matchField, string $preferenceType)
    {
        if (!$userId || $collection->isEmpty()) return $collection;

        $moduleId = config('module.current_module_data') ? config('module.current_module_data')['id'] : null;
        if (!$moduleId) return $collection;

        $summary = CustomerPreferenceSummary::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->first();

        if (!$summary) return $collection;

        $preferredIds = match($preferenceType) {
            'store' => $summary->top_stores ?? [],
            'item' => $summary->top_items ?? [],
            'category' => $summary->top_categories ?? [],
            default => [],
        };

        if (empty($preferredIds)) return $collection;

        $preferredFlipped = array_flip($preferredIds);

        return $collection->sortBy(function ($item) use ($matchField, $preferredFlipped) {
            $id = data_get($item, $matchField);
            return isset($preferredFlipped[$id]) ? $preferredFlipped[$id] : 9999;
        })->values();
    }
}
