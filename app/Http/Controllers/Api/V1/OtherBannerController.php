<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ModuleWiseBanner;
use App\Models\ModuleWiseWhyChoose;
use Illuminate\Http\Request;

class OtherBannerController extends Controller
{
    public function get_banners(Request $request)
    {
        $module = config('module.current_module_data');
        $module_id = $module['id'] ?? null;

        $banners = ModuleWiseBanner::Active()->where('module_id', $module_id)->where('type', 'promotional_banner')->get();

        $bannerData = [];

        if (($module['module_type'] ?? null) == 'parcel') {
            $bannerData['banners'] = $banners;
        } else {
            foreach ($banners as $banner) {
                $key = $banner->key;
                $value = $banner->value;
                $bannerData[$key] = $value;
                $bannerData[$key.'_full_url'] = Helpers::get_full_url('promotional_banner', $value, $banner?->storage[0]?->value ?? 'public');
            }
        }

        return response()->json($bannerData, 200);
    }

    public function get_video_content(Request $request)
    {
        $module_id = config('module.current_module_data')['id'] ?? null;

        $contentKeys = ['content1_title', 'content1_subtitle', 'content2_title', 'content2_subtitle', 'content3_title', 'content3_subtitle'];
        $bannerKeys = ['section_title', 'banner_type', 'banner_video', 'banner_image', 'banner_video_content'];

        $banners = ModuleWiseBanner::Active()->where('module_id', $module_id)->where('type', 'video_banner_content')
            ->whereIn('key', [...$bannerKeys, ...$contentKeys])
            ->get();

        $bannerData = [];
        $banner_contents = collect();

        foreach ($banners as $banner) {
            if (in_array($banner->key, $contentKeys, true)) {
                $banner_contents->push($banner);
                continue;
            }

            $key = $banner->key;
            $value = $banner->value;
            $bannerData[$key] = $value;
            if ($key == 'banner_video_content') {
                $bannerData[$key.'_full_url'] = Helpers::get_full_url('promotional_banner/video', $value, $banner?->storage[0]?->value ?? 'public');
            } elseif ($key == 'banner_image') {
                $bannerData[$key.'_full_url'] = Helpers::get_full_url('promotional_banner', $value, $banner?->storage[0]?->value ?? 'public');
            }
        }

        $data = ['banner_contents' => $banner_contents];
        $data = array_merge($data, $bannerData);

        return response()->json($data, 200);
    }

    public function get_why_choose(Request $request)
    {
        $module_id = config('module.current_module_data')['id'] ?? null;

        $banners = ModuleWiseWhyChoose::Active()->where('module_id', $module_id)->get();

        return response()->json(['banners' => $banners], 200);
    }
}
