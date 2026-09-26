<?php

use Modules\Service\Service\BusinessSettingService;

if (file_exists(('./Modules/RideShare/Lib/Helpers.php'))) {
    require_once ('./Modules/RideShare/Lib/Helpers.php');
    require_once ('./Modules/RideShare/Lib/ReverbPusherHelpers.php');
    require_once ('./Modules/RideShare/Lib/TripRequestUpdate.php');
}

if (! function_exists('service_api_module_active')) {
    function service_api_module_active(): bool
    {
        $module = config('module.current_module_data');

        return addon_published_status('Service')
            && $module
            && ($module['module_type'] ?? null) === 'service';
    }
}

if (! function_exists('service_addon_active')) {
    function service_addon_active(): bool
    {
        return addon_published_status('Service') === 1;
    }
}

if (! function_exists('service_setting_enabled')) {
    function service_setting_enabled(string $key): bool
    {
        return service_addon_active() && BusinessSettingService::isEnabled(key: $key);
    }
}
