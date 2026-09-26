<?php

namespace App\Builder;

use App\Models\Store;
use Illuminate\Http\Request;
use Modules\Builder\Contracts\StorefrontScopeResolver as StorefrontScopeResolverContract;
use Modules\Builder\Entities\TenantDomainConfig;
use Modules\Builder\ValueObjects\StorefrontScope;

class StorefrontScopeResolver implements StorefrontScopeResolverContract
{
    public function resolveFromRequest(Request $request): ?StorefrontScope
    {
        $store = $this->resolveStore($request);

        if (!$store) {
            return null;
        }

        return new StorefrontScope(
            tenantId: $store->vendor_id,
            subTenantId: $store->id,
            moduleId: $store->module_id,
            regionId: $store->zone_id,
            logoUrl: $this->safeLogoUrl($store),
            displayName: $store->slug ?? null,
            moduleType: $store->module_type, // accessor → $store->module->module_type
            coverImageUrl: $this->safeCoverImageUrl($store),
            contactNumber: $store->phone ?? null,
        );
    }

    private function resolveStore(Request $request): ?Store
    {
        $host = $this->normalizeHost($request->getHost());

        if ($host) {
            $store = $this->resolveStoreByDomain($host);
            if ($store) {
                return $store;
            }
        }

        $storeIdentifier = $request->query('store_id', $request->query('store'));

        if (!$storeIdentifier) {
            return null;
        }

        return Store::query()
            ->select(['id', 'vendor_id', 'slug', 'module_id', 'zone_id', 'logo', 'cover_photo', 'phone'])
            ->with(['module:id,module_type'])
            ->when(
                is_numeric($storeIdentifier),
                fn ($query) => $query->where('id', (int) $storeIdentifier),
                fn ($query) => $query->where('slug', $storeIdentifier)
            )
            ->first();
    }

    private function resolveStoreByDomain(string $host): ?Store
    {
        $domainConfig = TenantDomainConfig::query()
            ->select(['tenant_id', 'sub_tenant_id', 'website_visibility'])
            ->where(function ($query) use ($host) {
                $query->where('domain', $host);

                if (\str_starts_with($host, 'www.')) {
                    $query->orWhere('domain', \substr($host, 4));
                } else {
                    $query->orWhere('domain', 'www.' . $host);
                }
            })
            ->where(function ($query) {
                $query->where('is_connected', true)
                      ->orWhere('type', 'sub-domain');
            })
            ->first();

        if (!$domainConfig) {
            return null;
        }

        // Domain Settings → "Website Visibility" toggle. When the vendor
        // turns visibility off, the storefront is taken offline: refusing
        // to resolve the scope here makes `RequireStorefrontScope`
        // 404 the request. The vendor can still preview / edit in the
        // BuilderSetup admin (separate route, not gated by scope), per
        // the toggle's own description.
        if (!$domainConfig->website_visibility) {
            return null;
        }

        return Store::query()
            ->select(['id', 'vendor_id', 'slug', 'module_id', 'zone_id', 'logo', 'cover_photo', 'phone'])
            ->with(['module:id,module_type'])
            ->where('id', $domainConfig->sub_tenant_id)
            ->where('vendor_id', $domainConfig->tenant_id)
            ->first();
    }

    private function normalizeHost(?string $host): ?string
    {
        if (!$host) {
            return null;
        }

        return \strtolower(\trim($host));
    }

    private function safeLogoUrl(Store $store): ?string
    {
        try {
            return $store->logo_full_url;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeCoverImageUrl(Store $store): ?string
    {
        try {
            return $store->cover_photo_full_url;
        } catch (\Throwable) {
            return null;
        }
    }
}
