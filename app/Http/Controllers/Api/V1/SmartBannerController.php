<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\SmartBanner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SmartBannerController extends Controller
{
    public function get_banners(Request $request): JsonResponse
    {
        Helpers::setZoneIds($request);
        $zoneHeader = $request->header('zoneId');
        $zoneIds = json_decode($zoneHeader, true) ?: ($zoneHeader ? [$zoneHeader] : []);

        $now = Carbon::now();
        $todayDate = $now->toDateString();
        $nowTime = $now->format('H:i:s');

        $cacheKey = 'smart_banners_'.md5(implode('_', [
            json_encode($zoneIds),
            $todayDate,
            $nowTime,
        ]));

        $banners = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($zoneIds, $todayDate, $nowTime) {
            return SmartBanner::active()
                ->when(!empty($zoneIds), fn ($q) => $q->whereIn('zone_id', $zoneIds))
                ->where(function ($q) use ($todayDate) {
                    $q->where('active_days', 'everyday')
                        ->orWhere(function ($w) use ($todayDate) {
                            $w->where('active_days', 'custom_date')
                                ->where('start_date', '<=', $todayDate)
                                ->where('end_date', '>=', $todayDate);
                        });
                })
                ->where(function ($q) use ($nowTime) {
                    $q->whereNull('start_time')
                        ->orWhere(function ($w) use ($nowTime) {
                            $w->where('start_time', '<=', $nowTime)
                                ->where(function ($inner) use ($nowTime) {
                                    $inner->whereNull('end_time')
                                        ->orWhere('end_time', '>=', $nowTime);
                                });
                        });
                })
                ->with(['translations', 'module'])
                ->orderBy('position')
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'subtitle' => $banner->subtitle,
                        'image_full_url' => $banner->image_full_url,
                        'position' => $banner->position,
                        'redirect_type' => $banner->redirect_type,
                        'redirect_target_id' => $banner->redirect_target_id,
                        'module_id' => $banner->module_id,
                        'zone_id' => $banner->zone_id,
                        'active_days' => $banner->active_days,
                        'start_date' => $banner->start_date,
                        'end_date' => $banner->end_date,
                        'start_time' => $banner->start_time,
                        'end_time' => $banner->end_time,
                    ];
                })
                ->values();
        });

        return response()->json(['smart_banners' => $banners], 200);
    }
}
