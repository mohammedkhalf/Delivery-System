<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminServiceModuleCheckMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed|never
     */
    public function handle(Request $request, Closure $next)
    {
        if (addon_published_status('Service') && (config('module.current_module_type') == 'service')
            || $request->is('admin/service/booking/*')
            || $request->is('admin/service/provider/details*')
            || $request->is('admin/service/service-management/*')) {
            return $next($request);
        }

        return abort(404);
    }
}
