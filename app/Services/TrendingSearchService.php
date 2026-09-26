<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use App\Models\Module;
use App\Models\SearchLog;
use Illuminate\Support\Facades\Cache;
use Modules\Service\Entities\Service;

class TrendingSearchService
{
    public function getTrending(?int $moduleId, string $zoneId, bool $cache = true): array
    {
        $cacheKey = 'trending_searches_' . md5($zoneId . '_' . ($moduleId ?? 'global'));

        $zoneIds = $this->parseZoneIds($zoneId);

        $compute = function () use ($moduleId, $zoneIds) {
            $trending = SearchLog::selectRaw('keyword, COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN CONCAT("u_", user_id) ELSE CONCAT("g_", guest_id) END) as unique_users')
                ->when($moduleId, function ($q) use ($moduleId) {
                    $q->where('module_id', $moduleId);
                })
                ->when(! empty($zoneIds), function ($q) use ($zoneIds) {
                    $q->where(function ($qq) use ($zoneIds) {
                        foreach ($zoneIds as $z) {
                            $qq->orWhereRaw('JSON_CONTAINS(zone_id, ?)', [(string) $z]);
                        }
                    });
                })
                ->where('created_at', '>=', now()->subHours(6))
                ->where('result_count', '>', 0)
                ->whereRaw('LENGTH(TRIM(keyword)) >= 2')
                ->groupBy('keyword')
                ->havingRaw('unique_users >= 2')
                ->orderByDesc('unique_users')
                ->limit(10)
                ->pluck('keyword')
                ->toArray();

            if (! empty($trending)) {
                return $trending;
            }

            $fallback = $this->fallbackCategories($moduleId, $zoneIds);

            shuffle($fallback);

            return $fallback;
        };

        if (! $cache) {
            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
            }
            return $compute();
        }

        return Cache::remember($cacheKey, now()->addMinutes(15), $compute);
    }

    private function fallbackCategories(?int $moduleId, array $zoneIds): array
    {
        $moduleType = $moduleId ? Module::where('id', $moduleId)->value('module_type') : null;

        if ($moduleType === 'service' && class_exists(Service::class)) {
            return $this->serviceFallbackCategories($moduleId, $zoneIds);
        }

        $itemCategories = $this->itemFallbackCategories($moduleId, $zoneIds);

        if ($moduleId !== null || ! class_exists(Service::class)) {
            return $itemCategories;
        }

        $serviceCategories = $this->serviceFallbackCategories(null, $zoneIds);

        return $this->interleave($itemCategories, $serviceCategories, 10);
    }

    private function interleave(array $first, array $second, int $limit): array
    {
        $merged = [];
        $max = max(count($first), count($second));

        for ($i = 0; $i < $max && count($merged) < $limit; $i++) {
            foreach ([$first, $second] as $list) {
                if (isset($list[$i]) && ! in_array($list[$i], $merged, true) && count($merged) < $limit) {
                    $merged[] = $list[$i];
                }
            }
        }

        return $merged;
    }

    private function itemFallbackCategories(?int $moduleId, array $zoneIds): array
    {
        $hasAvailableItem = function ($itemQuery) use ($zoneIds, $moduleId) {
            $itemQuery->active(zone_ids: $zoneIds, module_id: $moduleId);
        };

        return Category::where('status', 1)
            ->where('position', 0)
            ->when($moduleId, function ($q) use ($moduleId) {
                $q->where('module_id', $moduleId);
            })
            ->where(function ($q) use ($hasAvailableItem) {
                $q->whereHas('products', $hasAvailableItem)
                    ->orWhereHas('childes.products', $hasAvailableItem);
            })
            ->orderByDesc('priority')
            ->limit(10)
            ->pluck('name')
            ->toArray();
    }

    private function serviceFallbackCategories(?int $moduleId, array $zoneIds): array
    {
        $categoryIds = Service::active(zone_ids: $zoneIds, module_id: $moduleId)
            ->get(['category_id', 'sub_category_id'])
            ->flatMap(fn ($service) => [$service->category_id, $service->sub_category_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($categoryIds)) {
            return [];
        }

        return Category::where('status', 1)
            ->whereIn('id', $categoryIds)
            ->orderByDesc('priority')
            ->limit(10)
            ->pluck('name')
            ->toArray();
    }

    public function log(string $keyword, ?int $userId, ?string $guestId, int $moduleId, string $zoneId, int $resultCount): void
    {
        if (strlen(trim($keyword)) < 2) {
            return;
        }

        SearchLog::create([
            'keyword'      => strtolower(trim($keyword)),
            'user_id'      => $userId,
            'guest_id'     => $guestId,
            'module_id'    => $moduleId,
            'zone_id'      => $zoneId,
            'result_count' => $resultCount,
        ]);
    }

    private function parseZoneIds(string $zoneId): array
    {
        $raw = trim($zoneId);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [$raw];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($v) => (int) $v, $decoded),
            fn ($v) => $v > 0
        )));
    }
}
