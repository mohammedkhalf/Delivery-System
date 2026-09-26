<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ZoneModuleException;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ModuleController extends Controller
{
    private const RESPONSE_FIELDS = [
        'id',
        'module_name',
        'module_type',
        'short_description',
        'stores_count',
        'slug',
        'items_count',
        'icon_full_url',
        'thumbnail_full_url',
        'zones',
    ];

    private const SELECT_COLUMNS = ['id', 'module_name', 'module_type', 'short_description', 'slug', 'icon', 'thumbnail'];

    public function index(Request $request)
    {
        if (! empty($request->zone_id)) {
            $zoneIds = [$request->zone_id];

            $modules = $this->withMetrics(
                Module::select(self::SELECT_COLUMNS)
                    ->with(['zones:id,name'])
                    ->whereHas('zones', fn ($query) => $query->whereIn('zone_id', $zoneIds))
                    ->notParcel()
                    ->active(),
                $zoneIds,
            )->get();

            Module::attachTopOffers($modules, $zoneIds);

            return response()->json($this->shape($modules));
        }

        $zone_id = $this->resolveZoneIds($request);

        $modules = $this->withMetrics(
            Module::select(self::SELECT_COLUMNS)
                ->with(['zones:id,name'])
                ->whereHas('zones', fn ($query) => $query->whereIn('zone_id', $zone_id))
                ->active(),
            $zone_id,
        )->get();

        Module::attachTopOffers($modules, $zone_id);

        return response()->json($this->shape($modules));
    }

    private function withMetrics($query, array $zoneIds)
    {
        $today = date('Y-m-d');

        return $query
            ->withCount([
                'items',
                'stores' => fn ($q) => $q
                    ->whereIn('zone_id', $zoneIds)
                    ->whereHas('vendor', fn ($v) => $v->where('status', 1)),
                'stores as free_delivery_count' => fn ($q) => $q
                    ->whereIn('zone_id', $zoneIds)
                    ->whereHas('vendor', fn ($v) => $v->where('status', 1))
                    ->where('free_delivery', 1),
            ])
            ->withTopOffer($zoneIds)
            ->selectSub(function ($q) use ($zoneIds) {
                $q->select('delivery_time')
                    ->from('stores')
                    ->whereColumn('stores.module_id', 'modules.id')
                    ->whereIn('stores.zone_id', $zoneIds)
                    ->where('stores.status', 1)
                    ->whereNotNull('stores.delivery_time')
                    ->orderByRaw('CASE '
                        .'WHEN delivery_time LIKE "%hours%" THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(delivery_time, "-", 1), " ", 1) AS UNSIGNED) * 60 '
                        .'WHEN delivery_time LIKE "%min%" OR delivery_time LIKE "%minute%" THEN CAST(SUBSTRING_INDEX(delivery_time, "-", 1) AS UNSIGNED) '
                        .'ELSE 9999 END ASC')
                    ->limit(1);
            }, 'min_delivery_time_range')
            ->selectSub(function ($q) use ($today) {
                $q->selectRaw('COUNT(*)')
                    ->from('flash_sales')
                    ->whereColumn('flash_sales.module_id', 'modules.id')
                    ->where('flash_sales.is_publish', 1)
                    ->whereDate('flash_sales.start_date', '<=', $today)
                    ->whereDate('flash_sales.end_date', '>=', $today);
            }, 'flash_sale_count');
    }

    public function topOffer(Request $request)
    {
        $moduleId = $request->header('moduleId');

        if (empty($moduleId) || !isset($moduleId)) {
            return response()->json(['errors' => [['code' => 'module', 'message' => translate('messages.not_found')]]], 404);
        }

        $zoneIds = $this->resolveZoneIds($request);

        $module = Module::select(['id'])
            ->where('id', $moduleId)
            ->active()
            ->first();

        if (! $module) {
            return response()->json(['errors' => [['code' => 'module', 'message' => translate('messages.not_found')]]], 404);
        }

        $modules = collect([$module]);
        Module::attachTopOffers($modules, $zoneIds);
        $module = $modules->first();

        return response()->json([
            'module_id' => (int) $module->id,
            'discount' => (float) ($module->top_offer_value ?? 0),
            'discount_type' => $module->top_offer_type ?? null,
        ]);
    }

    private function shape($modules)
    {
        return $modules->map(function ($module) {
            $row = Arr::only($module->toArray(), self::RESPONSE_FIELDS);

            if (isset($row['zones'])) {
                $row['zones'] = array_map(
                    fn ($zone) => ['id' => $zone['id'], 'name' => $zone['name']],
                    $row['zones']
                );
            }

            $row['top_offer'] = [
                'discount' => (float) ($module->top_offer_value ?? 0),
                'discount_type' => $module->top_offer_type ?? null,
            ];
            $row['min_delivery_time'] = $module->min_delivery_time_range ?: null;
            $row['flash_sale'] = ((int) ($module->flash_sale_count ?? 0)) > 0 ? 1 : 0;
            $row['free_delivery'] = ((int) ($module->free_delivery_count ?? 0)) > 0 ? 1 : 0;
            $row['description'] = $module->description;
            

            return $row;
        });
    }

    private function resolveZoneIds(Request $request): array
    {
        $header = $request->header('zoneId');

        if (empty($header)) {
            $zone = Zone::where('status', 1)->where('is_default', 1)->first()
                ?? Zone::first();

            if (! $zone) {
                throw new ZoneModuleException(translate('No zone is available'));
            }

            $header = \json_encode([$zone->id]);
            $request->headers->set('zoneId', $header);
        }

        $decoded = \json_decode($header, true);

        return \is_array($decoded) ? $decoded : [$decoded];
    }
}
