<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCanonicalHost
{
    /**
     * Funnel the `www`/apex variant of the panel host to the exact configured
     * APP_HOST_DOMAIN.
     *
     * The admin, vendor-panel and landing routes are domain-constrained to
     * config('app.host_domain'); on the *other* variant (e.g. www when the
     * canonical is the apex) they don't match, so the unconstrained storefront
     * route shadows them — the "home overridden by the Vendor Website Builder"
     * symptom. Redirecting to the canonical host makes those routes match.
     *
     * Tenant storefront hosts are entirely different domains, so they never
     * satisfy the same-registrable-domain check below and pass straight through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = config('app.host_domain');

        if ($canonical) {
            $host  = $request->getHost();
            $strip = static fn (string $h): string => preg_replace('/^www\./i', '', $h);

            // Same site reached via the www/apex counterpart of the panel host.
            if ($host !== $canonical && $strip($host) === $strip($canonical)) {
                return redirect()->to(
                    $request->getScheme() . '://' . $canonical . $request->getRequestUri(),
                    301
                );
            }
        }

        return $next($request);
    }
}
