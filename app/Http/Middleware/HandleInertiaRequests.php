<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // Expose Laravel session flashes as `pageProps.flash.*` for
            // any Inertia page that wants to toast / banner them.
            //   - controllers using `back()->with('success', '...')`
            //     surface as `flash.success`
            //   - same for `error` / `info`
            // BuilderFlashToaster (admin editor) and FlashToaster
            // (storefront) both read these keys; the storefront's
            // middleware additionally layers domain-specific flash keys
            // on top (auth_event, wallet_add_fund_result, etc.).
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'info'    => $request->session()->get('info'),
            ],
        ];
    }
}
