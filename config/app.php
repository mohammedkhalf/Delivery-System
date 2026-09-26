<?php

use App\Providers\InterfaceServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool)env('APP_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL', null),

    'app_mode' => env('APP_MODE', 'live'),

    /*
    |--------------------------------------------------------------------------
    | Host / Storefront domain split
    |--------------------------------------------------------------------------
    |
    | `host_domain` is the single hostname where the admin/vendor panel and
    | all `routes/*.php` host endpoints answer — e.g. `admin.6ammart.com`
    | or just `6ammart.com` if the host runs on the apex.
    |
    | Every host route group in `App\Providers\RouteServiceProvider` is
    | wrapped in `Route::domain(config('app.host_domain'))->group(...)`,
    | so a request on any other host (vendor sub-domain, custom domain)
    | falls through to the Builder module's storefront routes — which
    | resolve the active store from `tenant_domain_configs` and 404 if
    | nothing matches.
    |
    | `host_base_domain` is the parent zone where vendor sub-domain entries
    | live (typically the bare apex, e.g. `6ammart.com`). Used only to
    | compute reserved sub-domain labels in the vendor's domain-setup UI —
    | when the host runs on a sub-domain of the base, that label is
    | reserved against vendor input.
    |
    | When `APP_HOST_DOMAIN` is unset, host routes match any host (legacy
    | behavior). Leave both unset on a single-tenant install.
    |
    */

    'host_domain'      => env('APP_HOST_DOMAIN'),

    'host_base_domain' => env('APP_HOST_BASE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Public IP — surfaced to vendors in the Domain Settings instructions
    | so they know which A-record IP to point their custom domain at.
    | Leave unset on a dev / single-tenant install; the UI will fall back
    | to a "contact your administrator" hint.
    */

    'public_ip' => env('APP_PUBLIC_IP'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'cipher' => 'AES-256-CBC',

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
