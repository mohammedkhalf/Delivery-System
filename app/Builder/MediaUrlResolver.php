<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use Modules\Builder\Contracts\MediaUrlResolver as MediaUrlResolverContract;

class MediaUrlResolver implements MediaUrlResolverContract
{
    public function defaultLogoUrl(): ?string
    {
        try {
            return Helpers::logoFullUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    public function url(string $folder, string $filename, string $disk = 'public', ?string $type = null): string
    {
        return Helpers::get_full_url($folder, $filename, $disk, $type ?? 'upload_image');
    }

    public function assetUrl(string $path): string
    {
        // 6amMart's Laravel app is nested under a `public/` folder served as
        // root, so bundled assets live behind the `public/` prefix.
        return asset('public/' . \ltrim($path, '/'));
    }

    public function storageBaseUrl(): string
    {
        // 6amMart's public disk is served from /storage/app/public; expose that
        // true base so the storefront resolves relative/legacy media correctly.
        return asset('storage/app/public');
    }
}
