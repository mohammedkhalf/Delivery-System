<?php

namespace App\Builder;

use Illuminate\Support\Facades\Auth;
use Modules\Builder\Contracts\VendorAuthProvider as VendorAuthProviderContract;

/**
 * 6amMart host adapter for VendorAuthProvider.
 *
 * The vendor panel uses two Auth guards — `vendor` for the owner
 * account and `vendor_employee` for sub-accounts. Both can reach the
 * BuilderSetup editor, so we resolve whichever is logged in.
 *
 * `image_full_url` is a model accessor that may throw when the stored
 * filename can't be resolved on disk (broken upload, missing storage
 * disk). Wrapped in a try/catch so a single bad image doesn't crash
 * every page load.
 */
class VendorAuthProvider implements VendorAuthProviderContract
{
    public function current(): ?array
    {
        $user = Auth::guard('vendor')->user()
            ?: Auth::guard('vendor_employee')->user();

        if (!$user) {
            return null;
        }

        $name = \trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? ''));

        return [
            'name'      => $name !== '' ? $name : ($user->email ?? 'Vendor'),
            'email'     => $user->email ?? null,
            'image_url' => $this->safeImageUrl($user),
        ];
    }

    public function logoutUrl(): string
    {
        // 6amMart exposes a single `GET /logout` endpoint (named
        // `logout`) which dynamically dispatches based on the currently
        // authenticated guard — see App\Http\Controllers\LoginController
        // ::logout(). It auto-detects vendor vs vendor_employee vs
        // admin, looks up the dynamic login-slug via
        // Helpers::get_login_url() (read from the `data_settings`
        // table, so an operator can rename e.g. /vendor/login to
        // /partner/login), and redirects accordingly. We just hand the
        // URL to the front-end; the host does the dispatch.
        //
        // Fallback: if the route name is missing on a future host that
        // wires this contract differently, return a stable `/logout`
        // path so the dropdown link still navigates somewhere
        // recognisable. A custom adapter should override this method
        // when the host uses a different logout endpoint or a POST.
        try {
            return \route('logout');
        } catch (\Throwable) {
            return \url('/logout');
        }
    }

    private function safeImageUrl($user): ?string
    {
        try {
            return $user->image_full_url ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
