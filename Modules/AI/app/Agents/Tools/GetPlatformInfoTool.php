<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\BusinessSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPlatformInfoTool implements Tool
{
    /**
     * Only these keys may ever be read and returned to the AI.
     * All others — API keys, payment gateways, mail config, commissions, etc. — are excluded.
     */
    private const ALLOWED_KEYS = [
        'business_name',
        'address',
        'phone',
        'email_address',
        'country',
        'currency',
        'currency_symbol_position',
        'digit_after_decimal_point',
        'timezone',
        'timeformat',
        'additional_charge',
        'additional_charge_name',
        'additional_charge_status',
        'service_charge',
        'free_delivery_over',
        'free_delivery_over_status',
    ];

    public function __construct(
        private readonly AiResponseContext $context,
    ) {}

    public function description(): string
    {
        return 'Get public platform information: business name, contact address, phone, support email, country, currency symbol, decimal format, and any additional charges or free-delivery thresholds. Use this when the user asks about currency, pricing format, contact details, platform name, support info, delivery fees, or additional charges. Always use the returned currency when displaying prices.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $rows = BusinessSetting::whereIn('key', self::ALLOWED_KEYS)
            ->get(['key', 'value'])
            ->pluck('value', 'key')
            ->all();

        $this->context->recordTool('GetPlatformInfoTool');

        $info = $this->buildInfo($rows);

        if (empty($info)) {
            return 'Platform information is not configured.';
        }

        $lines = [];
        foreach ($info as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }

        return 'Platform info — ' . implode('; ', $lines);
    }

    private function buildInfo(array $rows): array
    {
        $info = [];

        if (!empty($rows['business_name'])) {
            $info['Platform name'] = $rows['business_name'];
        }

        if (!empty($rows['country'])) {
            $info['Country'] = $rows['country'];
        }

        if (!empty($rows['currency'])) {
            $symbol   = $rows['currency'];
            $position = $rows['currency_symbol_position'] ?? 'left';
            $decimals = (int) ($rows['digit_after_decimal_point'] ?? 2);
            $info['Currency'] = $symbol;
            $info['Currency position'] = $position;
            $info['Decimal places'] = $decimals;
            $info['Price format example'] = $position === 'right'
                ? '100.' . str_repeat('0', $decimals) . $symbol
                : $symbol . '100.' . str_repeat('0', $decimals);
        }

        if (!empty($rows['address'])) {
            $info['Address'] = $rows['address'];
        }

        if (!empty($rows['phone'])) {
            $info['Phone'] = $rows['phone'];
        }

        if (!empty($rows['email_address'])) {
            $info['Support email'] = $rows['email_address'];
        }

        if (!empty($rows['timezone'])) {
            $info['Timezone'] = $rows['timezone'];
        }

        // Additional charge
        $additionalStatus = ($rows['additional_charge_status'] ?? '0') == '1';
        if ($additionalStatus && isset($rows['additional_charge'])) {
            $name             = $rows['additional_charge_name'] ?? 'Additional charge';
            $info[$name]      = $rows['additional_charge'];
        }

        // Service charge
        if (isset($rows['service_charge']) && $rows['service_charge'] > 0) {
            $info['Service charge'] = $rows['service_charge'];
        }

        // Free delivery threshold
        $freeDeliveryActive = ($rows['free_delivery_over_status'] ?? '0') == '1';
        if ($freeDeliveryActive && isset($rows['free_delivery_over'])) {
            $currency = $rows['currency'] ?? '';
            $info['Free delivery over'] = $currency . $rows['free_delivery_over'];
        }

        return $info;
    }
}
