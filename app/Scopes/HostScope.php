<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Request-aware scope for identity-bearing models (`users` and the auth
 * aux tables: `password_resets`, `phone_verifications`,
 * `email_verifications`). Three branches:
 *
 *   1. Backend operators bypass the scope entirely — they need to see
 *      every row regardless of storefront, exactly as they do today. The
 *      guard list is `config('auth.host_scope_bypass_guards')` (defaults
 *      to admin / vendor / vendor_employee). Adding a new backend guard
 *      later is a one-line config edit, not a HostScope edit.
 *
 *   2. Storefront requests (Builder middleware has bound a non-null
 *      `StorefrontContext` scope) filter to that scope. This is what
 *      lets `Auth::guard('customer')->attempt(...)` find storefront
 *      users — Laravel's EloquentUserProvider applies global scopes
 *      during credential lookup, so without this branch a storefront
 *      login would never resolve.
 *
 *   3. All other requests (host mobile API V1, host site, unauthenticated
 *      anonymous pages) default to host (0, 0) — strict host-customer
 *      isolation.
 *
 * If Builder is not installed, branch 2 never fires (no `StorefrontContext`
 * in the container or its scope is null), so behavior collapses to "host
 * only," which is the correct single-tenant default.
 */
class HostScope implements Scope
{
    /**
     * Fallback list when `config('auth.host_scope_bypass_guards')` is
     * unset — covers the guards present in this app today. Keeping the
     * default here means a fresh install (or one that hasn't edited
     * config/auth.php) gets correct behavior out of the box.
     */
    private const DEFAULT_BYPASS_GUARDS = ['admin', 'vendor', 'vendor_employee'];

    public function apply(Builder $builder, Model $model): void
    {
        // Branch 1: backend operators see everything. List is config-driven
        // so host code can add new backend guards without editing this file.
        // `config('auth.guards.$g')` check skips guards that don't exist in
        // this install — defensive against drifted setups.
        $bypassGuards = (array) \config('auth.host_scope_bypass_guards', self::DEFAULT_BYPASS_GUARDS);
        foreach ($bypassGuards as $guard) {
            if (\config("auth.guards.$guard") && Auth::guard($guard)->check()) {
                return;
            }
        }

        // Branch 2: storefront request — filter to the current storefront's
        // scope so `Auth::guard('customer')->attempt(...)` and other model
        // lookups can find storefront-scoped users. The container only has
        // `StorefrontContext` bound when the Builder module is loaded, and
        // even then the scope is only non-null after the
        // `ShareStorefrontProps` middleware has resolved it.
        //
        // Class name MUST NOT have a leading backslash — Laravel's
        // container registers bindings under the bare FQN, and
        // `app()->bound()` does a string match (no normalization).
        [$tenantId, $subTenantId] = [0, 0];
        $contextClass = 'Modules\\Builder\\Services\\StorefrontContext';
        if (\class_exists($contextClass) && app()->bound($contextClass)) {
            $scope = app($contextClass)->getScope();
            if ($scope !== null) {
                $tenantId    = (int) ($scope->tenantId ?? 0);
                $subTenantId = (int) ($scope->subTenantId ?? 0);
            }
        }

        // Branch 3: default host filter (or storefront filter if branch 2
        // populated the values above).
        $table = $model->getTable();
        $builder->where("$table.tenant_id", $tenantId)
                ->where("$table.sub_tenant_id", $subTenantId);
    }
}
