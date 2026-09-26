<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Traits\AddonHelper;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    use AddonHelper;

    private const BUILDER_MODULE_PATH    = 'Modules/Builder';
    private const BUILDER_ADAPTER_PATH   = 'Builder';
    private const BUILDER_CONTRACT_NS    = 'Modules\\Builder\\Contracts\\';
    private const BUILDER_ADAPTER_NS     = 'App\\Builder\\';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBuilderBindings();
    }

    /**
     * Auto-discover host adapters in app/Builder/ and bind each one
     * to whichever Modules\Builder\Contracts\* interface it implements.
     */
    private function registerBuilderBindings(): void
    {
        // Gate on the 6amMart addon-activation status, not just disk
        // presence. A customer may have installed Builder (files
        // extracted to Modules/Builder/) but not yet clicked "Activate"
        // in the admin panel — in that state `is_published` is 0 and
        // the host adapters should stay dormant.
        if (!\addon_published_status('Builder')) {
            return;
        }

        $adapterPath = app_path(self::BUILDER_ADAPTER_PATH);

        if (!is_dir($adapterPath)) {
            return;
        }

        foreach (glob($adapterPath . '/*.php') as $file) {
            $class = self::BUILDER_ADAPTER_NS . basename($file, '.php');

            if (!class_exists($class)) {
                continue;
            }

            foreach (class_implements($class) ?: [] as $interface) {
                if (str_starts_with($interface, self::BUILDER_CONTRACT_NS)) {
                    $this->app->bind($interface, $class);
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        //TODO: need to remove after 3.8 development
        if (app()->environment('local')) {
            if (request()->header('x-forwarded-proto') === 'https' || request()->getScheme() === 'https') {
                \URL::forceScheme('https');
            }
            if(request()->header('x-forwarded-host')) {
                \URL::forceRootUrl('https://' . request()->header('x-forwarded-host'));
            }
        }

        try
        {
            Request::macro('isAny', function (array $patterns) {
                return collect($patterns)->contains(fn ($pattern) => Request::is($pattern));
            });

            Config::set('addon_admin_routes',$this->get_addon_admin_routes());
            Config::set('get_payment_publish_status',$this->get_payment_publish_status());
            Paginator::useBootstrap();
            foreach(Helpers::get_view_keys() as $key=>$value)
            {
                view()->share($key, $value);
            }
        }
        catch(\Exception $e)
        {

        }

    }
}
