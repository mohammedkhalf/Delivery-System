<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\BusinessSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetAvailableLanguagesTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
    ) {}

    public function description(): string
    {
        return 'Return the list of languages that are currently active and supported on this platform. Use this when the user explicitly asks "what languages do you support?" or "which languages are available?".';
    }

    public function schema(JsonSchema $_schema): array
    {
        return [];
    }

    public function handle(Request $_request): string
    {
        $this->context->recordTool('GetAvailableLanguagesTool');

        $languages = self::loadActive();

        if (empty($languages)) {
            return 'No language configuration found.';
        }

        $default = '';
        $names   = [];
        foreach ($languages as $lang) {
            $name = self::localeName($lang['code']);
            if (!empty($lang['default'])) {
                $default = $name;
            }
            $names[] = $name . ' (' . $lang['code'] . ')';
        }

        $list = implode(', ', $names);
        return "Supported languages: {$list}." . ($default ? " Default: {$default}." : '');
    }

    /**
     * Load active languages from business_settings.system_language.
     * A language is considered active if status=1 OR it is the default language.
     * Returns array of ['code' => ..., 'direction' => ..., 'default' => ...].
     */
    public static function loadActive(): array
    {
        $raw = BusinessSetting::where('key', 'system_language')->value('value');
        if (!$raw) {
            return [];
        }

        $all = json_decode($raw, true);
        if (!\is_array($all)) {
            return [];
        }

        return array_values(array_filter($all, function ($l): bool {
            return is_array($l) && (!empty($l['status']) || !empty($l['default']));
        }));
    }

    /**
     * Map a locale code to a human-readable language name.
     */
    public static function localeName(string $code): string
    {
        $map = [
            'en'    => 'English',
            'ar'    => 'Arabic',
            'bn'    => 'Bengali',
            'fr'    => 'French',
            'de'    => 'German',
            'es'    => 'Spanish',
            'it'    => 'Italian',
            'pt'    => 'Portuguese',
            'ru'    => 'Russian',
            'zh'    => 'Chinese',
            'ja'    => 'Japanese',
            'ko'    => 'Korean',
            'tr'    => 'Turkish',
            'nl'    => 'Dutch',
            'pl'    => 'Polish',
            'sv'    => 'Swedish',
            'da'    => 'Danish',
            'no'    => 'Norwegian',
            'fi'    => 'Finnish',
            'hi'    => 'Hindi',
            'ur'    => 'Urdu',
            'fa'    => 'Persian',
            'id'    => 'Indonesian',
            'ms'    => 'Malay',
            'th'    => 'Thai',
            'vi'    => 'Vietnamese',
            'el'    => 'Greek',
            'he'    => 'Hebrew',
            'ro'    => 'Romanian',
            'hu'    => 'Hungarian',
            'cs'    => 'Czech',
            'sk'    => 'Slovak',
            'uk'    => 'Ukrainian',
            'bg'    => 'Bulgarian',
            'hr'    => 'Croatian',
            'sr'    => 'Serbian',
            'lt'    => 'Lithuanian',
            'lv'    => 'Latvian',
            'et'    => 'Estonian',
            'sl'    => 'Slovenian',
            'sq'    => 'Albanian',
            'mk'    => 'Macedonian',
            'bs'    => 'Bosnian',
            'ka'    => 'Georgian',
            'az'    => 'Azerbaijani',
            'kk'    => 'Kazakh',
            'uz'    => 'Uzbek',
            'hy'    => 'Armenian',
            'sw'    => 'Swahili',
            'am'    => 'Amharic',
            'ne'    => 'Nepali',
            'si'    => 'Sinhala',
            'my'    => 'Burmese',
            'km'    => 'Khmer',
            'lo'    => 'Lao',
            'mn'    => 'Mongolian',
            'tl'    => 'Filipino',
            'jv'    => 'Javanese',
        ];

        return $map[strtolower($code)] ?? strtoupper($code);
    }
}
