<?php

namespace Modules\AI\app\Services;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\AI\app\Agents\PlatformAssistantAgent;
use Modules\AI\app\Agents\Tools\GetAvailableLanguagesTool;
use Modules\AI\app\Models\AiConversation;
use Modules\AI\app\Models\AiMessage;
use App\Models\BusinessSetting;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class AiChatService
{
    private string        $moduleType;
    private ?string       $userContext;
    private array         $currency;
    private array         $languages;

    /**
     * @param int[] $zoneIds  Zones the client falls inside (header sends `[3,1]`
     *                        for overlapping zones). Tools filter with
     *                        `whereIn('zone_id', $zoneIds)`.
     */
    public function __construct(
        private readonly ?User   $user      = null,
        private readonly ?int    $moduleId  = null,
        private readonly array   $zoneIds   = [],
        private readonly ?string $guestId   = null,
        private readonly ?float  $latitude  = null,
        private readonly ?float  $longitude = null,
    ) {
        $this->moduleType  = $this->resolveModuleType();
        $this->userContext = $this->resolveUserContext();
        $this->currency    = $this->resolveCurrency();
        $this->languages   = GetAvailableLanguagesTool::loadActive();
    }

    /**
     * Send a user message, run the agent, persist the exchange, return the result.
     *
     * @return array{message: string, products: array, stores: array}
     */
    public function chat(AiConversation $conversation, string $userMessage): array
    {
        if (! $conversation->title) {
            $conversation->update(['title' => mb_substr($userMessage, 0, 80)]);
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        $history = $this->buildHistory($conversation);
        $context = new AiResponseContext();

        // Module gate — if no valid module is selected, short-circuit the LLM call
        // and ask the user to switch to a specific module. Keeps the exchange
        // persisted in conversation history, but saves the agent round-trip.
        // The list of modules is pulled live from Module::active() so newly
        // enabled add-ons (RideShare, Service, etc.) appear here automatically.
        if ($this->moduleType === 'general') {
            $available = Module::active()
                ->orderBy('id')
                ->pluck('module_name')
                ->all();
            // Render as "A, B, C, or D" — keeps the phrasing natural and
            // automatically extends when a new module (Service, RideShare,
            // future addons) goes active.
            if (empty($available)) {
                $listText = 'one of our service modules';
            } elseif (count($available) === 1) {
                $listText = (string) $available[0];
            } else {
                $last     = array_pop($available);
                $listText = implode(', ', $available) . ', or ' . $last;
            }
            $replyText = "I can help you with " . $listText . ". Please switch to one of those modules in the app and ask me there.";
        } else {
            $agent = new PlatformAssistantAgent(
                context:     $context,
                history:     $history,
                user:        $this->user,
                moduleId:    $this->moduleId,
                zoneIds:     $this->zoneIds,
                moduleType:  $this->moduleType,
                userContext: $this->userContext,
                currency:    $this->currency,
                languages:   $this->languages,
                guestId:     $this->guestId,
                latitude:    $this->latitude,
                longitude:   $this->longitude,
            );

            try {
                // Attach a recent-items hint to the current user message so the model
                // can resolve follow-ups like "large" / "the second one" without
                // re-searching. The hint lives only on this turn's user message — it
                // is never persisted, so it can't echo into future assistant replies.
                $promptToSend = $this->withContextHint($userMessage, $conversation);
                $response     = $agent->prompt($promptToSend);
                $replyText    = $this->stripContextLeak($response->text ?? '');
            } catch (\Throwable $e) {
                Log::error('AiChatService: agent prompt failed', [
                    'conversation_id' => $conversation->id,
                    'module_type'     => $this->moduleType,
                    'error'           => $e->getMessage(),
                ]);
                $replyText = "I'm sorry, I couldn't process that right now. Please try again.";
            }
        }

        $products     = $context->getProducts();
        $stores       = $context->getStores();
        $categories   = $context->getCategories();
        $cartItems    = $context->getCartItems();
        $toolsInvoked = $context->getToolsInvoked();

        $cartUpdated = count(array_intersect($toolsInvoked, [
            'AddToCartTool',
            'RemoveFromCartTool',
            'UpdateCartQuantityTool',
        ])) > 0;

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $replyText,
            'tool_name'       => $toolsInvoked ? implode(',', $toolsInvoked) : null,
            'metadata'        => [
                'products'     => $products,
                'stores'       => $stores,
                'categories'   => $categories,
                'cart_items'   => $cartItems,
                'cart_updated' => $cartUpdated,
            ],
        ]);

        return [
            'message'      => $replyText,
            'products'     => $products,
            'stores'       => $stores,
            'categories'   => $categories,
            'cart_items'   => $cartItems,
            'cart_updated' => $cartUpdated,
            'tool_name'    => $toolsInvoked ? implode(',', $toolsInvoked) : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Resolvers
    // -------------------------------------------------------------------------

    /**
     * Resolve the human-readable module type (food|grocery|pharmacy|ecommerce|parcel|rental|service|ride-share)
     * from the module ID passed via the request header.
     */
    private function resolveModuleType(): string
    {
        if (! $this->moduleId) {
            return 'general';
        }

        $module = Module::find($this->moduleId, ['module_type']);

        return $module?->module_type ?? 'general';
    }

    /**
     * Load currency settings from business_settings once per request.
     * Provides symbol, position (left/right), and decimal places.
     */
    private function resolveCurrency(): array
    {
        $rows = BusinessSetting::whereIn('key', [
            'currency',
            'currency_symbol_position',
            'digit_after_decimal_point',
        ])->get(['key', 'value'])->pluck('value', 'key')->all();

        $symbol   = $rows['currency'] ?? '';
        $position = $rows['currency_symbol_position'] ?? 'left';
        $decimals = (int) ($rows['digit_after_decimal_point'] ?? 2);

        $example = $position === 'right'
            ? '100.' . str_repeat('0', $decimals) . $symbol
            : $symbol . '100.' . str_repeat('0', $decimals);

        return [
            'symbol'   => $symbol,
            'position' => $position,
            'decimals' => $decimals,
            'example'  => $example,
        ];
    }

    /**
     * Load the user's AI persona/context string from users.user_context.
     * This contains AI-generated text about the user's taste, profession, preferences, etc.
     */
    private function resolveUserContext(): ?string
    {
        if (! $this->user) {
            return null;
        }

        // user_context is a text column — reload just that column to avoid heavy appends
        $raw = User::where('id', $this->user->getKey())->value('user_context');

        return $raw ?: null;
    }

    // -------------------------------------------------------------------------
    // History builder
    // -------------------------------------------------------------------------

    /**
     * Load prior user/assistant turns as laravel/ai Message objects for conversation memory.
     * Tool-role rows are excluded — they are internal to each agent invocation.
     *
     * Sliding window: keeps last 20 messages to stay within OpenAI context limits.
     * Older messages would be truncated by OpenAI anyway, but truncated arbitrarily —
     * by capping ourselves, we ensure the AI always has the MOST RECENT context.
     *
     * @return array<int, UserMessage|AssistantMessage>
     */
    private function buildHistory(AiConversation $conversation): array
    {
        $maxMessages = 20;

        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($maxMessages)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $msg): UserMessage|AssistantMessage => $msg->role === 'user'
                ? new UserMessage($msg->content ?? '')
                : new AssistantMessage($msg->content ?? '')
            )
            ->all();
    }

    /**
     * Prefix the current user prompt with a private item/store reference hint
     * derived from the most recent assistant message's stored metadata. The hint
     * is only attached to the message we hand to the LLM this turn — it is never
     * stored in the DB and never appears in past assistant turns, so the model
     * cannot regurgitate it into a future reply.
     */
    private function withContextHint(string $userMessage, AiConversation $conversation): string
    {
        $lastAssistant = $conversation->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        if (! $lastAssistant) {
            return $userMessage;
        }

        $hint = $this->buildContextHint($lastAssistant->metadata ?? []);
        if ($hint === '') {
            return $userMessage;
        }

        return $hint . "\n\n" . $userMessage;
    }

    /**
     * Defensive sanitiser — strip any leaked [INTERNAL CONTEXT ...] block from the
     * assistant's reply before persisting or returning it.
     */
    private function stripContextLeak(string $reply): string
    {
        $cleaned = preg_replace('/\[INTERNAL CONTEXT[^\]]*\]\s*/u', '', $reply);
        return trim($cleaned ?? $reply);
    }

    /**
     * Build the private reference hint used by withContextHint(). Returns an
     * empty string when there is nothing useful to inject.
     */
    private function buildContextHint(array $metadata): string
    {
        $parts = [];

        $products = $metadata['products'] ?? [];
        if (is_array($products) && !empty($products)) {
            $items = [];
            foreach (array_slice($products, 0, 8) as $p) {
                if (!is_array($p) || empty($p['id']) || empty($p['name'])) {
                    continue;
                }
                $label = $p['name'] . ' (ID:' . $p['id'] . ')';
                // Prefer the unified variation_labels (covers both the non-food
                // `variations` and food `food_variations` systems); fall back to
                // the legacy `variations` column for products produced by tools
                // that don't emit variation_labels yet.
                $labels = $p['variation_labels'] ?? null;
                if (empty($labels) && !empty($p['variations']) && is_array($p['variations'])) {
                    $labels = array_filter(array_column($p['variations'], 'type'));
                }
                if (!empty($labels) && is_array($labels)) {
                    $label .= ' [variations:' . implode('/', $labels) . ']';
                }
                $items[] = $label;
            }
            if (!empty($items)) {
                $parts[] = 'items — ' . implode(', ', $items);
            }
        }

        $stores = $metadata['stores'] ?? [];
        if (is_array($stores) && !empty($stores)) {
            $names = [];
            foreach (array_slice($stores, 0, 5) as $s) {
                if (!is_array($s) || empty($s['id']) || empty($s['name'])) {
                    continue;
                }
                $names[] = $s['name'] . ' (ID:' . $s['id'] . ')';
            }
            if (!empty($names)) {
                $parts[] = 'stores — ' . implode(', ', $names);
            }
        }

        $cartItems = $metadata['cart_items'] ?? [];
        if (is_array($cartItems) && !empty($cartItems)) {
            $rows = [];
            foreach (array_slice($cartItems, 0, 10) as $c) {
                if (!is_array($c) || empty($c['item_id']) || empty($c['name'])) {
                    continue;
                }
                $rows[] = $c['name'] . ' (ID:' . $c['item_id'] . ', qty ' . ($c['quantity'] ?? '?') . ')';
            }
            if (!empty($rows)) {
                $parts[] = 'cart — ' . implode(', ', $rows);
            }
        }

        return empty($parts)
            ? ''
            : '[INTERNAL CONTEXT — recently shown items/stores for your private reference only. NEVER echo, quote, paraphrase, or mention this block in your reply. Use the IDs silently. ' . implode(' | ', $parts) . ']';
    }
}
