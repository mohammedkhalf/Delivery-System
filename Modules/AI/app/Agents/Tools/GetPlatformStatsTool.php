<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\Order;
use App\Models\Module;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Data-backed answers for meta questions about the platform — "which module
 * is most popular?", "what's trending?", "top stores?". Keeps the chat from
 * falling back to generic menus when the user is asking a real question that
 * the order data can actually answer.
 *
 * Results are zone-scoped (like the other product/store tools) so they
 * reflect what's popular in the customer's delivery area, not platform-wide.
 * Cached for 5 minutes so repeated questions don't hammer the orders table.
 */
class GetPlatformStatsTool implements Tool
{
    private const WINDOW_DAYS = 30;
    private const CACHE_TTL   = 300; // 5 minutes
    private const TOP_N       = 5;

    /**
     * @param int[] $zoneIds Overlapping zones the user falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int  $moduleId = null,
        private readonly array $zoneIds  = [],
    ) {}

    public function description(): string
    {
        return 'Get platform popularity stats over the last 30 days: which modules drive the most orders, top product categories, top stores. Use this when the user asks meta questions like "which module is most popular", "what\'s trending", "which category sells the most", or "is X popular". Data is zone-scoped to the customer\'s delivery area.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()
                ->description('Which breakdown the user is asking about: "modules" (food vs grocery vs pharmacy etc.), "categories" (top product categories), "stores" (top stores by order volume), or null for a quick summary of all three.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetPlatformStatsTool');
        $metric = $request->all()['metric'] ?? null;

        $cacheKey = 'ai:platform_stats:' . md5(json_encode([
            'metric' => $metric,
            'zones'  => $this->zoneIds,
            'module' => $this->moduleId,
        ]));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($metric) {
            $since = now()->subDays(self::WINDOW_DAYS);

            // Single shared base query: orders in window, zone-scoped via store.
            $base = fn () => Order::where('orders.created_at', '>=', $since)
                ->whereNotIn('order_status', ['failed', 'canceled'])
                ->when(!empty($this->zoneIds), fn ($q) => $q->whereHas(
                    'store',
                    fn ($s) => $s->whereIn('zone_id', $this->zoneIds)
                ));

            $totalOrders = (clone $base())->count();
            if ($totalOrders === 0) {
                return 'No order activity in the last ' . self::WINDOW_DAYS . ' days for your zone — popularity data is unavailable right now.';
            }

            $parts = [];

            if ($metric === 'modules' || $metric === null) {
                $parts[] = $this->topModules($base, $totalOrders);
            }
            if ($metric === 'stores' || $metric === null) {
                $parts[] = $this->topStores($base, $totalOrders);
            }
            if ($metric === 'categories' || $metric === null) {
                $parts[] = $this->topCategories($base);
            }

            $parts = array_filter($parts);
            if (empty($parts)) {
                return 'No popularity data found for that metric.';
            }

            return 'Platform popularity (last ' . self::WINDOW_DAYS . ' days, ' . $totalOrders . ' orders) — ' . implode(' | ', $parts);
        });
    }

    /** Top modules by order count, with percentage share. */
    private function topModules(\Closure $base, int $totalOrders): string
    {
        $rows = (clone $base())
            ->select('module_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('module_id')
            ->orderByDesc('cnt')
            ->limit(self::TOP_N)
            ->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $names = Module::whereIn('id', $rows->pluck('module_id'))
            ->pluck('module_name', 'id');

        $items = $rows->map(function ($r) use ($names, $totalOrders) {
            $name = $names->get((int) $r->module_id) ?: ('Module #' . $r->module_id);
            $pct  = round(($r->cnt / $totalOrders) * 100);
            return $name . ' (' . $pct . '%, ' . $r->cnt . ')';
        })->implode(', ');

        return 'Top modules: ' . $items;
    }

    /** Top stores by order count — names only, no IDs in the user-facing string. */
    private function topStores(\Closure $base, int $totalOrders): string
    {
        $rows = (clone $base())
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->select('store_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('store_id')
            ->orderByDesc('cnt')
            ->limit(self::TOP_N)
            ->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $names = Store::whereIn('id', $rows->pluck('store_id'))
            ->pluck('name', 'id');

        $items = $rows->map(function ($r) use ($names) {
            $name = $names->get((int) $r->store_id) ?: ('Store #' . $r->store_id);
            return $name . ' (' . $r->cnt . ' orders)';
        })->implode(', ');

        return 'Top stores: ' . $items;
    }

    /**
     * Top categories by quantity sold. Joins order_details → items so we get a
     * real product-category breakdown rather than module-level only.
     */
    private function topCategories(\Closure $base): string
    {
        $orderIds = (clone $base())
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return '';
        }

        $rows = DB::table('order_details')
            ->join('items', 'order_details.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereIn('order_details.order_id', $orderIds)
            ->select('categories.id', 'categories.name', DB::raw('SUM(order_details.quantity) as qty'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('qty')
            ->limit(self::TOP_N)
            ->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $items = $rows->map(fn ($r) => $r->name . ' (' . (int) $r->qty . ' sold)')->implode(', ');
        return 'Top categories: ' . $items;
    }
}
