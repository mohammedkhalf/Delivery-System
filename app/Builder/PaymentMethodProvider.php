<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\PaymentMethodProvider as PaymentMethodProviderContract;

class PaymentMethodProvider implements PaymentMethodProviderContract
{
    public function digitalPaymentMethods(): array
    {
        $rows = DB::table('addon_settings')
            ->where('settings_type', 'payment_config')
            ->where('is_active', 1)
            ->whereNotNull('additional_data')
            ->orderBy('key_name')
            ->get(['key_name', 'additional_data']);

        $methods = [];
        foreach ($rows as $row) {
            $extra = json_decode((string) $row->additional_data, true);
            if (!\is_array($extra)) {
                continue;
            }

            $title = trim((string) ($extra['gateway_title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $image = null;
            $filename = $extra['gateway_image'] ?? null;
            if (\is_string($filename) && $filename !== '') {
                $image = Helpers::get_full_url(
                    'payment_modules/gateway_image',
                    $filename,
                    \is_string($extra['storage'] ?? null) ? $extra['storage'] : 'public',
                );
            }

            $methods[] = [
                'key'   => (string) $row->key_name,
                'label' => $title,
                'image' => $image,
            ];
        }

        usort($methods, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

        return $methods;
    }
}
