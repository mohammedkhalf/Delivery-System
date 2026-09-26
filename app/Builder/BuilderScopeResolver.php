<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Modules\Builder\Contracts\BuilderScopeResolver as BuilderScopeResolverContract;
use Modules\Builder\ValueObjects\StorefrontScope;

class BuilderScopeResolver implements BuilderScopeResolverContract
{
    public function resolveFromAuth(): ?StorefrontScope
    {
        if (!Auth::guard('vendor')->check() && !Auth::guard('vendor_employee')->check()) {
            return null;
        }

        $vendorId = Helpers::get_vendor_id() ?: null;
        $storeId  = Helpers::get_store_id() ?: null;
        $store    = $storeId ? $this->loadStore($storeId) : null;

        return new StorefrontScope(
            tenantId: $vendorId,
            subTenantId: $storeId,
            moduleId: $store?->module_id,
            regionId: $store?->zone_id,
            logoUrl: $this->safeLogoUrl($store),
            displayName: $store?->slug ?? null,
        );
    }

    private function loadStore(int $storeId): ?Store
    {
        return Store::query()
            ->select(['id', 'vendor_id', 'slug', 'module_id', 'zone_id', 'logo'])
            ->find($storeId);
    }

    private function safeLogoUrl(?Store $store): ?string
    {
        if (!$store) {
            return null;
        }

        try {
            return $store->logo_full_url;
        } catch (\Throwable) {
            return null;
        }
    }
}
