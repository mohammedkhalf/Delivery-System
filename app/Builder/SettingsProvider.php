<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Modules\Builder\Contracts\SettingsProvider as SettingsProviderContract;

class SettingsProvider implements SettingsProviderContract
{
    public function brandName(): ?string
    {
        return Helpers::get_business_settings('business_name');
    }

    public function mapApiKey(): ?string
    {
        return Helpers::get_business_settings('map_api_key');
    }

    public function referralEarningRate(): float
    {
        return (float) (BusinessSetting::query()
            ->where('key', 'ref_earning_exchange_rate')
            ->value('value') ?? 0);
    }

    public function firebaseConfig(): ?array
    {
        // Same `fcm_credentials` row the host's SW generator and dispatch
        // pipeline use, so token issuance, SW handler, and push delivery
        // all point at one Firebase project. Null when essentials missing.
        $config = Helpers::get_business_settings('fcm_credentials');
        if (!\is_array($config) || empty($config['apiKey']) || empty($config['projectId']) || empty($config['vapidKey'])) {
            return null;
        }

        return [
            'apiKey'            => $config['apiKey'] ?? '',
            'authDomain'        => $config['authDomain'] ?? '',
            'projectId'         => $config['projectId'] ?? '',
            'storageBucket'     => $config['storageBucket'] ?? '',
            'messagingSenderId' => $config['messagingSenderId'] ?? '',
            'appId'             => $config['appId'] ?? '',
            'measurementId'     => $config['measurementId'] ?? '',
            'vapidKey'          => $config['vapidKey'] ?? '',
        ];
    }
}
