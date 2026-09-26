<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProviderServiceModuleCheckMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (addon_published_status('Service') && \App\CentralLogics\Helpers::get_store_data()?->module_type == 'service') {
            return $next($request);
        }
        return abort(404);

    }
}
