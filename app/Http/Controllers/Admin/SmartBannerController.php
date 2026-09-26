<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Module;
use App\Models\SmartBanner;
use App\Models\Store;
use App\Models\Translation;
use App\Models\Zone;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SmartBannerController extends Controller
{
    private const ALLOWED_POSITIONS = ['top', 'bottom'];
    private const ALLOWED_REDIRECTS = ['category', 'module_home', 'store_page', 'offer_page'];
    private const MODULE_HOME_ONLY_TYPES = ['parcel', 'ride-share'];
    private const PROVIDER_MODULE_TYPES = ['rental', 'service'];
    private const IMAGE_DIR = 'smart-banner';

    public function index(Request $request, $zone_id): View
    {
        $zone = Zone::findOrFail($zone_id);
        $language = \getWebConfig('language') ?? [];
        $modules = Module::whereHas('zones', function ($q) use ($zone_id) {
            $q->where('zone_id', $zone_id);
        })->get();
        $positions = [
            'top' => translate('messages.top'),
            'bottom' => translate('messages.bottom'),
        ];

        $banners = SmartBanner::where('zone_id', $zone_id)
            ->when($request->search, function ($q) use ($request) {
                $keywords = explode(' ', $request->search);
                $q->where(function ($inner) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $inner->orWhereHas('translations', function ($t) use ($kw) {
                            $t->where('key', 'title')->where('value', 'like', "%{$kw}%");
                        });
                    }
                });
            })
            ->latest('id')
            ->paginate(config('default_pagination'));

        return view('admin-views.zone.smart-banner.index', compact('zone', 'banners', 'language', 'modules', 'positions'));
    }

    public function store(Request $request, $zone_id): JsonResponse
    {
        Zone::findOrFail($zone_id);
        $payload = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $imageName = Helpers::upload(dir: self::IMAGE_DIR . '/', format: 'png', image: $request->file('image'), maxSizeMb: 2, allowedExtensions: 'jpg,jpeg,png,svg');

            $banner = new SmartBanner();
            $banner->zone_id = $zone_id;
            $this->applyPayload($banner, $payload);
            $banner->image = $imageName;
            $banner->created_by = 'admin';

            $this->assertNoPositionOverlap($banner);

            $banner->save();

            $this->saveTranslations($request, $banner);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => translate('messages.smart_banner_created_successfully'),
                'data' => $this->formatForJson($banner->fresh()),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse($e);
        }
    }

    public function edit($id): JsonResponse
    {
        $banner = SmartBanner::withoutGlobalScopes(['translate'])->with('translations')->findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $this->formatForJson($banner, true),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $banner = SmartBanner::findOrFail($id);
        $payload = $this->validatePayload($request, $banner);

        DB::beginTransaction();
        try {
            if ($request->hasFile('image')) {
                if ($banner->image) {
                    Storage::disk(Helpers::getDisk())->delete(self::IMAGE_DIR . '/' . $banner->image);
                }
                $banner->image = Helpers::upload(dir: self::IMAGE_DIR . '/', format: 'png', image: $request->file('image'), maxSizeMb: 2, allowedExtensions: 'jpg,jpeg,png,svg');
            }

            $this->applyPayload($banner, $payload);

            $this->assertNoPositionOverlap($banner);

            $banner->save();

            $this->saveTranslations($request, $banner);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => translate('messages.smart_banner_updated_successfully'),
                'data' => $this->formatForJson($banner->fresh()),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse($e);
        }
    }

    public function view($id): JsonResponse
    {
        $banner = SmartBanner::with(['translations', 'module'])->findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $this->formatForJson($banner),
        ]);
    }

    public function status($id, $status): RedirectResponse
    {
        $banner = SmartBanner::findOrFail($id);
        $banner->status = (bool) $status;
        $banner->save();
        Toastr::success(translate('messages.smart_banner_status_updated'));
        return back();
    }

    public function destroy($id): RedirectResponse
    {
        $banner = SmartBanner::findOrFail($id);
        if ($banner->image) {
            Storage::disk(Helpers::getDisk())->delete(self::IMAGE_DIR . '/' . $banner->image);
        }
        $banner->translations()->delete();
        $banner->storage()->delete();
        $banner->delete();
        Toastr::success(translate('messages.smart_banner_deleted_successfully'));
        return back();
    }

    public function categoriesByModule($module_id): JsonResponse
    {
        $categories = Category::where('module_id', $module_id)
            ->where('position', 0)
            ->where('status', 1)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);
        return response()->json(['status' => true, 'data' => $categories]);
    }

    public function storesByModuleZone($module_id, $zone_id): JsonResponse
    {
        $stores = Store::withoutGlobalScopes()
            ->where('module_id', $module_id)
            ->where('zone_id', $zone_id)
            ->where('status', 1)
            ->get(['id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);
        return response()->json(['status' => true, 'data' => $stores]);
    }

    private function validatePayload(Request $request, ?SmartBanner $existing = null): array
    {
        if ($request->module_id === 'all') {
            $request->merge(['module_id' => null]);
        }

        $module = $request->module_id ? Module::find($request->module_id) : null;
        $allowedRedirects = $this->allowedRedirectsForModule($module);

        $rules = [
            'module_id' => 'required|exists:modules,id',
            'active_days' => 'required|in:everyday,custom_date',
            'date_range' => 'required_if:active_days,custom_date',
            'time_range' => 'nullable|string',
            'position' => 'required|in:' . implode(',', self::ALLOWED_POSITIONS),
            'redirect_type' => 'required|in:' . implode(',', $allowedRedirects),
            'redirect_target_id' => 'nullable|required_if:redirect_type,store_page|integer',
            'title' => 'required|array',
            'title.0' => 'required|string|max:50',
            'subtitle' => 'nullable|array',
            'image' => ($existing && $existing->image) ? 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048' : 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
        ];

        $messages = [
            'module_id.required' => translate('messages.please_select_a_module'),
            'redirect_target_id.required_if' => $this->isProviderModule($module)
                ? translate('messages.please_select_a_provider')
                : translate('messages.please_select_a_store'),
            'title.0.required' => translate('messages.default_title_required'),
            'image.required' => translate('messages.banner_image_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            abort(response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422));
        }

        $startDate = null;
        $endDate = null;
        $startTime = null;
        $endTime = null;

        if ($request->active_days === 'custom_date' && $request->filled('date_range')) {
            [$startRaw, $endRaw] = array_pad(explode(' - ', $request->date_range), 2, null);
            if ($startRaw && $endRaw) {
                $startDate = Carbon::parse(trim($startRaw))->toDateString();
                $endDate = Carbon::parse(trim($endRaw))->toDateString();
            }
        }

        if ($request->filled('time_range')) {
            [$startRaw, $endRaw] = array_pad(explode(' - ', $request->time_range), 2, null);
            if ($startRaw) {
                $startTime = Carbon::parse(trim($startRaw))->format('H:i:s');
            }
            if ($endRaw) {
                $endTime = Carbon::parse(trim($endRaw))->format('H:i:s');
            }
        }

        return [
            'module_id' => $request->module_id ?: null,
            'active_days' => $request->active_days,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'position' => $request->position,
            'redirect_type' => $request->redirect_type,
            'redirect_target_id' => in_array($request->redirect_type, ['module_home', 'offer_page'], true) ? null : ($request->redirect_target_id ?: null),
        ];
    }

    private function allowedRedirectsForModule(?Module $module): array
    {
        if (!$module) {
            return self::ALLOWED_REDIRECTS;
        }
        if (in_array($module->module_type, self::MODULE_HOME_ONLY_TYPES, true)) {
            return ['module_home'];
        }
        if (in_array($module->module_type, self::PROVIDER_MODULE_TYPES, true)) {
            return ['module_home', 'store_page'];
        }

        return self::ALLOWED_REDIRECTS;
    }

    private function isProviderModule(?Module $module): bool
    {
        return $module && in_array($module->module_type, self::PROVIDER_MODULE_TYPES, true);
    }

    private function applyPayload(SmartBanner $banner, array $payload): void
    {
        foreach ($payload as $key => $value) {
            $banner->{$key} = $value;
        }
    }

    private function saveTranslations(Request $request, SmartBanner $banner): void
    {
        $defaultIndex = is_array($request->lang) ? array_search('default', $request->lang, true) : 0;
        $defaultTitle = is_array($request->title) ? ($request->title[$defaultIndex] ?? null) : null;
        $defaultSubtitle = is_array($request->subtitle) ? ($request->subtitle[$defaultIndex] ?? null) : null;

        Helpers::add_or_update_translations($request, 'title', 'title', 'SmartBanner', $banner->id, $defaultTitle);
        Helpers::add_or_update_translations($request, 'subtitle', 'subtitle', 'SmartBanner', $banner->id, $defaultSubtitle);

        $this->fillEnglishFallback($request, $banner, $defaultTitle, $defaultSubtitle);
    }

    private function fillEnglishFallback(Request $request, SmartBanner $banner, ?string $defaultTitle, ?string $defaultSubtitle): void
    {
        if (!is_array($request->lang)) {
            return;
        }

        $enIndex = array_search('en', $request->lang, true);
        if ($enIndex === false) {
            return;
        }

        $fallbacks = [
            'title' => [$request->title[$enIndex] ?? null, $defaultTitle],
            'subtitle' => [$request->subtitle[$enIndex] ?? null, $defaultSubtitle],
        ];

        foreach ($fallbacks as $key => [$enValue, $defaultValue]) {
            if (filled($enValue) || blank($defaultValue)) {
                continue;
            }

            Translation::updateOrCreate(
                [
                    'translationable_type' => 'App\\Models\\SmartBanner',
                    'translationable_id' => $banner->id,
                    'locale' => 'en',
                    'key' => $key,
                ],
                ['value' => $defaultValue]
            );
        }
    }

    private function assertNoPositionOverlap(SmartBanner $banner): void
    {
        $query = SmartBanner::withoutGlobalScopes()
            ->where('zone_id', $banner->zone_id)
            ->where('position', $banner->position)
            ->where('status', 1);

        if ($banner->id) {
            $query->where('id', '!=', $banner->id);
        }

        $candidates = $query->get();
        foreach ($candidates as $other) {
            $datesOverlap = SmartBanner::datesOverlap(
                $banner->active_days,
                $banner->start_date ? Carbon::parse($banner->start_date)->toDateString() : null,
                $banner->end_date ? Carbon::parse($banner->end_date)->toDateString() : null,
                $other->active_days,
                $other->start_date ? Carbon::parse($other->start_date)->toDateString() : null,
                $other->end_date ? Carbon::parse($other->end_date)->toDateString() : null,
            );

            $timesOverlap = SmartBanner::timesOverlap(
                $banner->start_time,
                $banner->end_time,
                $other->start_time,
                $other->end_time,
            );

            if ($datesOverlap && $timesOverlap) {
                abort(response()->json([
                    'status' => false,
                    'message' => translate('messages.this_banner_overlaps_with_another_in_the_same_position._please_change_the_position_or_reschedule.'),
                ], 409));
            }
        }
    }

    private function formatForJson(SmartBanner $banner, bool $forEdit = false): array
    {
        $banner->loadMissing(['translations', 'module']);

        $titles = [];
        $subtitles = [];
        foreach ($banner->translations as $t) {
            if ($t->key === 'title') {
                $titles[$t->locale] = $t->value;
            } elseif ($t->key === 'subtitle') {
                $subtitles[$t->locale] = $t->value;
            }
        }
        if (!isset($titles['default']) || $titles['default'] === null) {
            $titles['default'] = $banner->getAttributes()['title'] ?? ($titles[array_key_first($titles) ?? null] ?? null);
        }
        if (!isset($subtitles['default']) || $subtitles['default'] === null) {
            $subtitles['default'] = $banner->getAttributes()['subtitle'] ?? ($subtitles[array_key_first($subtitles) ?? null] ?? null);
        }

        $title = $titles[app()->getLocale()] ?? $titles['default'] ?? '';
        $subtitle = $subtitles[app()->getLocale()] ?? $subtitles['default'] ?? '';

        $startDateFormatted = $banner->start_date ? Carbon::parse($banner->start_date)->format('m/d/Y') : null;
        $endDateFormatted = $banner->end_date ? Carbon::parse($banner->end_date)->format('m/d/Y') : null;


        $timeRangeFormatted = translate('messages.all_day');
        if ($banner->start_time) {
            $startTime = Carbon::parse($banner->start_time)->format('g:i A');
            $endTime = $banner->end_time
                ? Carbon::parse($banner->end_time)->format('g:i A')
                : translate('messages.until_you_turn_off');
            $timeRangeFormatted = $startTime . ' - ' . $endTime;
        }

        $redirectTypeLabels = [
            'category' => translate('messages.category'),
            'module_home' => translate('messages.module_home'),
            'store_page' => $this->isProviderModule($banner->module)
                ? translate('messages.provider_page')
                : translate('messages.store_page'),
            'offer_page' => translate('messages.offer_page'),
        ];

        $targetLabel = $this->resolveTargetLabel($banner);

        return [
            'id' => $banner->id,
            'zone_id' => $banner->zone_id,
            'module_id' => $banner->module_id,
            'module_name' => $banner->module ? translate($banner->module->module_name) : null,
            'active_days' => $banner->active_days,
            'start_date' => $banner->start_date,
            'end_date' => $banner->end_date,
            'start_date_formatted' => $startDateFormatted,
            'end_date_formatted' => $endDateFormatted,
            'start_time' => $banner->start_time,
            'end_time' => $banner->end_time,
            'time_range_formatted' => $timeRangeFormatted,
            'position' => $banner->position,
            'redirect_type' => $banner->redirect_type,
            'redirect_type_label' => $redirectTypeLabels[$banner->redirect_type] ?? $banner->redirect_type,
            'redirect_target_id' => $banner->redirect_target_id,
            'redirect_target_label' => $targetLabel,
            'status' => (bool) $banner->status,
            'image' => $banner->image,
            'image_full_url' => $banner->image_full_url,
            'title' => $title,
            'subtitle' => $subtitle,
            'titles' => $titles,
            'subtitles' => $subtitles,
        ];
    }

    private function resolveTargetLabel(SmartBanner $banner): ?string
    {
        if (!$banner->redirect_target_id) {
            return null;
        }
        if ($banner->redirect_type === 'category') {
            return optional(Category::find($banner->redirect_target_id))->name;
        }
        if ($banner->redirect_type === 'store_page') {
            return optional(Store::withoutGlobalScopes()->find($banner->redirect_target_id))->name;
        }
        return null;
    }

    private function errorResponse(\Throwable $e): JsonResponse
    {
        if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException && $e->getResponse() instanceof JsonResponse) {
            return $e->getResponse();
        }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getResponse() instanceof JsonResponse) {
            return $e->getResponse();
        }
        return response()->json([
            'status' => false,
            'message' => $e->getMessage() ?: 'Server error',
        ], 500);
    }
}
