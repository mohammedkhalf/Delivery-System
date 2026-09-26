<?php

namespace App\Builder;

use App\Models\BusinessSetting;
use Modules\Builder\Contracts\LocaleProvider as LocaleProviderContract;

class LocaleProvider implements LocaleProviderContract
{
    public function availableLanguages(): array
    {
        try {
            $setting = BusinessSetting::where('key', 'system_language')->first();

            if (!$setting) {
                return [self::baselineEnglish()];
            }

            return collect(json_decode($setting->value, true))
                ->where('status', 1)
                ->map(fn ($language) => [
                    'code'      => $language['code'] ?? 'en',
                    // 6amMart's system_language rows carry no display name, so
                    // fall back to the uppercased code. Name + flag come from the
                    // host so the storefront switcher renders a real label/flag
                    // instead of a generic globe.
                    'name'      => $language['name'] ?? strtoupper((string) ($language['code'] ?? 'en')),
                    'direction' => $language['direction'] ?? 'ltr',
                    'default'   => (bool) ($language['default'] ?? false),
                    'flag'      => self::flagUrl($language['code'] ?? 'en'),
                ])
                ->values()
                ->toArray();
        } catch (\Throwable) {
            return [self::baselineEnglish()];
        }
    }

    private static function baselineEnglish(): array
    {
        return ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'default' => true, 'flag' => self::flagUrl('en')];
    }

    /**
     * Flag image URL from the shared admin asset set (same source the
     * admin/vendor headers use). The file name is the language code, so a flag
     * exists for every enabled language; a missing PNG degrades gracefully to
     * the emoji/globe fallback in the storefront switcher.
     */
    private static function flagUrl(string $code): string
    {
        return asset('public/assets/admin/img/flags/' . strtolower($code) . '.png');
    }
}
