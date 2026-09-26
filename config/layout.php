<?php

/*
|--------------------------------------------------------------------------
| Admin / Vendor Layout Configuration
|--------------------------------------------------------------------------
|
| Central switch for the navigation layout (sidebar + navbar) used in both
| the admin panel and the vendor panel.
|
| - 'version' picks which design renders:
|     'v1'   -> legacy layout only
|     'v2'   -> modern rail+panel layout only
|     'auto' -> v2 for module workspaces listed in 'v2_modules', v1 elsewhere
|               (matches the prior hard-coded behavior)
|
| - 'v2_modules' is consulted only when version === 'auto'.
|
| - 'features' toggles individual v2-only UI elements. They have no effect
|   when version === 'v1'. Set to false to hide.
|
| Edit values directly; run `php artisan config:clear` if config cache is on.
|
*/

return [

    'version' => 'v2',

    'v2_modules' => [
        'grocery',
        'food',
        'pharmacy',
        'ecommerce',
        'parcel',
        'rental',
        'ride-share',
        'service',
        'users',
        'transactions',
        'dispatch',
        'settings',
    ],

    'features' => [
        'pin'                 => false,
        'compact_mode_toggle' => true,
        'fullscreen'          => true,
    ],

];
