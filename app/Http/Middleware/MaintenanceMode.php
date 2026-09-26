<?php

namespace App\Http\Middleware;

use App\CentralLogics\Helpers;
use Closure;
use Illuminate\Http\Request;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        if (Helpers::is_vendor_panel_maintenance_active()) {
            return to_route('maintenance_mode');
        }

        return $next($request);
    }
}
