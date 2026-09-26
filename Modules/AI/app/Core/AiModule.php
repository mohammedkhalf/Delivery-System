<?php

namespace Modules\AI\app\Core;

use App\CentralLogics\Helpers;

class AiModule
{
    public static function isChatActive(): bool
    {
        return (bool) (Helpers::get_business_settings('ai_chat_status') ?? 0)
            && self::isOpenAiConfigured();
    }

    public static function isPersonalizationActive(): bool
    {
        return (bool) (Helpers::get_business_settings('customer_personalization_status') ?? 0);
    }

    public static function isOpenAiConfigured(): bool
    {
        $config = Helpers::get_business_settings('openai_config');
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        return (int) data_get($config, 'status') === 1;
    }
}
