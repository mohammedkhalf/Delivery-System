<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use App\Models\Module;

class CurrentModule
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (request()->get('module_id')) {
            session()->put('current_module',request()->get('module_id'));
            Config::set('module.current_module_id', request()->get('module_id'));
        }else{
            Config::set('module.current_module_id', session()->get('current_module'));
        }

        $module_id = Config::get('module.current_module_id');
        $module_id = is_array($module_id)?null:$module_id;
        $module = isset($module_id)?Module::with('translations')->find($module_id):Module::with('translations')->active()->first();

        if ($module) {
            Config::set('module.current_module_id', $module->id);
            Config::set('module.current_module_type', $module->module_type);
            Config::set('module.current_module_name', $module->module_name);
        }else{
            Config::set('module.current_module_id', null);
            Config::set('module.current_module_type', 'settings');
        }
        if (Request::is('admin/users*')) {
            Config::set('module.current_module_id', null);
            Config::set('module.current_module_type', 'users');
        }
        if (Request::is('admin/transactions*')) {
            Config::set('module.current_module_id', null);
            Config::set('module.current_module_type', 'transactions');
        }
        if (Request::is('admin/dispatch*')) {
            Config::set('module.current_module_id', null);
            Config::set('module.current_module_type', 'dispatch');
        }
        if (Request::is('admin/business-settings/*') || Request::is('taxvat/*') || Request::is('admin/pro-customer*')) {
            Config::set('module.current_module_id', null);
            Config::set('module.current_module_type', 'settings');
        }

        // When navigating into an order/trip/ride detail page from a report,
        // swap the workspace to the matching module so the sidebar + top-bar
        // module indicator update. Reports pages themselves (the `*/report/*`
        // and ride-share `transaction*` URLs) keep the Reports workspace.
        $detailModuleType = null;
        $detailModuleId   = null;
        if (Request::is('admin/transactions/parcel/*') || Request::is('admin/parcel/*')) {
            $detailModuleType = 'parcel';
        } elseif (Request::is('admin/transactions/rental/trip/*') || Request::is('admin/rental/*')) {
            $detailModuleType = 'rental';
        } elseif (Request::is('admin/service/*') || Request::is('admin/service')) {
            $detailModuleType = 'service';
        } elseif (
            Request::is('admin/ride-share/*')
            || (Request::is('admin/transactions/ride-share/*')
                && ! Request::is('admin/transactions/ride-share/report/*')
                && ! Request::is('admin/transactions/ride-share/transaction*'))
        ) {
            $detailModuleType = 'ride-share';
        }

        if ($detailModuleType) {
            $matched = Module::where('module_type', $detailModuleType)->where('status', 1)->first();
            if ($matched) {
                Config::set('module.current_module_id', $matched->id);
                Config::set('module.current_module_type', $matched->module_type);
                Config::set('module.current_module_name', $matched->module_name);
                session()->put('current_module', $matched->id);
            } else {
                Config::set('module.current_module_type', $detailModuleType);
            }
        }

        return $next($request);
    }
}
