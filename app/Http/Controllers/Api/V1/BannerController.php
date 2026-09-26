<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Banner;
use App\Models\Campaign;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\BannerLogic;
use App\CentralLogics\PersonalizationService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;


class BannerController extends Controller
{
    public function get_banners(Request $request)
    {
        if (!config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $resolvedModule = getModule($request->header('moduleId'));
            if ($resolvedModule) {
                config(['module.current_module_data' => $resolvedModule]);
            }
        }

        if (service_api_module_active()) {
            Helpers::setZoneIds($request);
            $zone_ids = json_decode($request->header('zoneId'), true) ?? [];
            $module_id = config('module.current_module_data')['id'] ?? null;
            $banners = \Modules\Service\Lib\BannerLogic::get_banners($zone_ids, $module_id, $request->query('featured'));

            $campaigns = $request->query('featured')
                ? []
                : \Modules\Service\Lib\BasicCampaignLogic::customerList($zone_ids, $module_id);

            return response()->json(['campaigns' => $campaigns, 'banners' => $banners], 200);
        }

        if (!config('module.current_module_data') && addon_published_status('Service')) {
            return $this->all_module_banners($request);
        }

        Helpers::setZoneIds($request);
        $zone_id= $request->header('zoneId');
        $banners = BannerLogic::get_banners($zone_id, $request->query('featured'));
        $campaigns = $request->featured ? [] : $this->product_campaigns($zone_id);

        try {
            if(auth('api')->check() && is_array($banners)){
                $bannersCollection = collect($banners);
                $bannersCollection = PersonalizationService::reorderByPreference($bannersCollection, auth('api')->id(), 'store_id', 'store');
                $banners = $bannersCollection->toArray();
            }
            return response()->json(['campaigns'=>Helpers::basic_campaign_data_formatting($campaigns, true),'banners'=>$banners], 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    private function all_module_banners(Request $request)
    {
        Helpers::setZoneIds($request);
        $featured = $request->query('featured');
        $zone_id = $request->header('zoneId');
        $zone_ids = json_decode($zone_id, true) ?? [];

        $productBanners = BannerLogic::get_banners($zone_id, $featured);
        $serviceBanners = service_addon_active()
            ? \Modules\Service\Lib\BannerLogic::get_banners($zone_ids, null, $featured)
            : [];

        $ids = array_unique(array_merge(array_column($productBanners, 'id'), array_column($serviceBanners, 'id')));
        $serviceBannerIds = empty($ids) ? [] : Banner::whereIn('id', $ids)
            ->whereHas('module', fn ($q) => $q->where('module_type', 'service'))
            ->pluck('id')->all();
        $serviceSet = array_flip($serviceBannerIds);

        $banners = [];
        foreach ($productBanners as $banner) {
            if (!isset($serviceSet[$banner['id']])) {
                $banners[] = $this->normalize_banner($banner);
            }
        }
        foreach ($serviceBanners as $banner) {
            if (isset($serviceSet[$banner['id']])) {
                $banners[] = $this->normalize_banner($banner);
            }
        }

        $campaigns = [];
        if (!$featured) {
            $productCampaigns = Helpers::basic_campaign_data_formatting($this->product_campaigns($zone_id), true);
            $serviceCampaigns = service_addon_active()
                ? \Modules\Service\Lib\BasicCampaignLogic::customerList($zone_ids, null)
                : [];
            $campaigns = array_merge(is_array($productCampaigns) ? $productCampaigns : [], $serviceCampaigns);
        }

        return response()->json(['campaigns' => $campaigns, 'banners' => $banners], 200);
    }

    private function normalize_banner(array $banner): array
    {
        return [
            'id' => $banner['id'] ?? null,
            'title' => $banner['title'] ?? null,
            'type' => $banner['type'] ?? null,
            'image' => $banner['image'] ?? null,
            'image_full_url' => $banner['image_full_url'] ?? null,
            'link' => $banner['link'] ?? null,
            'store' => $banner['store'] ?? null,
            'item' => $banner['item'] ?? null,
            'provider' => $banner['provider'] ?? null,
            'category' => $banner['category'] ?? null,
            'service' => $banner['service'] ?? null,
        ];
    }

    private function product_campaigns($zone_id)
    {
        $moduleData = config('module.current_module_data');
        $moduleId = isset($moduleData['id']) ? $moduleData['id'] : 'default';
        $cacheKey = 'campaigns_' . md5($zone_id . '_' . $moduleId);

        return Cache::remember($cacheKey, now()->addMinutes(20), function() use ($zone_id) {
            return Campaign::whereHas('module.zones', function($query) use($zone_id) {
                $query->whereIn('zones.id', json_decode($zone_id, true));
            })
                ->when(config('module.current_module_data'), function($query) use($zone_id) {
                    $query->module(config('module.current_module_data')['id']);
                    if (!config('module.current_module_data')['all_zone_service']) {
                        $query->whereHas('stores', function($q) use($zone_id) {
                            $q->whereIn('zone_id', json_decode($zone_id, true));
                        });
                    }
                })
                ->running()
                ->active()
                ->get();
        });
    }

    public function get_store_banners(Request $request,$store_id)
    {
        if (service_api_module_active()) {
            Helpers::setZoneIds($request);
            $zone_ids = json_decode($request->header('zoneId'), true) ?? [];
            $module_id = config('module.current_module_data')['id'] ?? null;

            return response()->json(\Modules\Service\Lib\BannerLogic::get_store_banners($zone_ids, $module_id, $store_id), 200);
        }

       Helpers::setZoneIds($request);
        $zone_id= $request->header('zoneId');
        $moduleData = config('module.current_module_data');
        $moduleId = isset($moduleData['id']) ? $moduleData['id'] : 'default';
        $cacheKey = 'banners_' . md5(implode('_', [
                $zone_id,
                $moduleId,
                $store_id
            ]));
        $banners = Cache::remember($cacheKey, now()->addMinutes(20), function() use ($zone_id, $store_id) {
            $banners = Banner::active();

            if (config('module.current_module_data')) {
                $banners = $banners->whereHas('zone.modules', function($query) {
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })
                    ->module(config('module.current_module_data')['id'])
                    ->when(!config('module.current_module_data')['all_zone_service'], function($query) use ($zone_id) {
                        $query->whereIn('zone_id', json_decode($zone_id, true));
                    });
            }

            $banners = $banners->whereIn('zone_id', json_decode($zone_id, true))
                ->whereHas('module', function($query) {
                    $query->active();
                })
                ->where('data', $store_id)
                ->where('created_by', 'store')
                ->get();

            return $banners;
        });

        return response()->json($banners, 200);
    }
}
