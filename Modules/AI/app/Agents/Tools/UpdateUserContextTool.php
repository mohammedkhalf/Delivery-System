<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateUserContextTool implements Tool
{
    private const MAX_CHARS = 2000;

    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user       = null,
        private readonly string            $moduleType = 'general',
    ) {}

    public function description(): string
    {
        return 'Update the persistent user personality/preference memory. Call this ONLY when the user reveals significant information about their nature: dietary preferences (veg/vegan/halal), profession, lifestyle, budget sensitivity, favourite cuisines or brands, allergies, household size, or other strong personal signals. Do NOT call this for every message — only for genuinely revealing insights. The insight will be stored in compressed form and merged with existing context; old or redundant entries are removed automatically when the limit is reached.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'insight' => $schema->string()
                ->description('A concise, compressed summary of the new insight about the user (max ~500 chars). Examples: "Vegetarian. Prefers South Indian food. Budget-conscious." or "Software engineer. Orders late-night snacks frequently." Write in third-person, noun-phrase style.')
                ->required(),
            'module_scope' => $schema->string()
                ->description(
                    'Scope of this insight. Use "global" for cross-module facts (profession, budget, household size, allergies). ' .
                    'Use the module name for module-specific preferences: "food", "grocery", "pharmacy", "ecommerce", "parcel", "rental", "service", "ride-share". ' .
                    'Examples: dietary identity → "global"; favourite cuisine → "food"; prefers organic produce → "grocery"; prefers generic medicine brands → "pharmacy".'
                )
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        if (!$this->user) {
            return 'User context update skipped: no authenticated user.';
        }

        $args    = $request->all();
        $insight = trim((string) ($args['insight'] ?? ''));
        if ($insight === '') {
            return 'User context update skipped: empty insight.';
        }

        // Determine scope tag: explicit > current module > global
        $scopeRaw = trim((string) ($args['module_scope'] ?? ''));
        $scope    = $scopeRaw !== '' && $scopeRaw !== 'null' ? $scopeRaw : $this->moduleType;
        $scope    = \in_array($scope, ['general', ''], true) ? 'global' : $scope;

        $tagged  = "[{$scope}] {$insight}";
        $current = trim((string) ($this->user->getAttribute('user_context') ?? ''));
        $updated = $this->merge($current, $tagged, $scope);

        User::where('id', $this->user->getKey())->update(['user_context' => $updated]);

        $this->context->recordTool('UpdateUserContextTool');

        return "User context updated ({$scope} scope).";
    }

    private function merge(string $existing, string $taggedInsight, string $scope): string
    {
        if ($existing === '') {
            return $taggedInsight;
        }

        $facts = $this->splitFacts($existing);

        // Remove any existing fact that shares the same scope prefix and overlaps in content
        // (first 30 chars of the bare insight used as fingerprint)
        $bareInsight = preg_replace('/^\[[^\]]+\]\s*/', '', $taggedInsight);
        $fingerprint = strtolower(substr($bareInsight, 0, 30));

        $facts = array_values(array_filter($facts, function (string $fact) use ($scope, $fingerprint): bool {
            $factScope = $this->extractScope($fact);
            if ($factScope !== $scope) {
                return true; // different module — keep
            }
            $bareFact = strtolower(preg_replace('/^\[[^\]]+\]\s*/', '', $fact) ?? '');
            return strpos($bareFact, $fingerprint) === false;
        }));

        $facts[] = $taggedInsight;
        $merged  = $this->joinFacts($facts);

        if (strlen($merged) <= self::MAX_CHARS) {
            return $merged;
        }

        // Over limit — drop oldest facts from the front until it fits,
        // but never drop the newly added insight (last element)
        while (count($facts) > 1 && strlen($this->joinFacts($facts)) > self::MAX_CHARS) {
            array_shift($facts);
        }

        return $this->joinFacts($facts);
    }

    private function extractScope(string $fact): string
    {
        if (preg_match('/^\[([^\]]+)\]/', $fact, $m)) {
            return $m[1];
        }
        return 'global';
    }

    private function splitFacts(string $context): array
    {
        // Split on newlines; each tagged fact lives on its own line
        $parts = preg_split('/\n+/', $context, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $parts ?: [])));
    }

    private function joinFacts(array $facts): string
    {
        return implode("\n", array_values(array_filter(array_map('trim', $facts))));
    }
}
