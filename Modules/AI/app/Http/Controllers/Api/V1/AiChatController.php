<?php

namespace Modules\AI\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\AI\app\Models\AiConversation;
use Modules\AI\app\Models\AiMessage;
use App\Models\User;
use App\Models\Zone;
use Modules\AI\app\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class AiChatController extends Controller
{
    /**
     * Send a message and get an AI response.
     *
     * POST /api/v1/customer/ai-chat/send
     *
     * Body:
     *   - message (required)
     *   - conversation_id (optional) — continue an existing conversation
     */
    public function send(Request $request): JsonResponse
    {
        // Demo-mode hit limit — mirrors the product data-generation cap
        // (ProductAutoFillController): allow only 10 AI chat messages per IP so
        // the public demo isn't drained. A distinct cache key gives chat its own
        // budget, separate from product auto-fill.
        if (getEnvMode() == 'demo') {
            $ip       = $request->header('x-forwarded-for') ?: $request->ip();
            $cacheKey = 'restricted_ai_chat_ip_' . $ip;
            $hits     = Cache::store('file')->get($cacheKey, 0);

            if ($hits >= 10) {
                return response()->json([
                    'errors' => [[
                        'code'    => 'demo_limit',
                        'message' => translate('Demo Mode Restriction: AI chat can only be used 10 times in demo mode. Further attempts are disabled to maintain a fair demo experience.'),
                    ]],
                ], 403);
            }

            Cache::store('file')->forever($cacheKey, $hits + 1);
        }

        $validator = Validator::make($request->all(), [
            'message'         => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
            'guest_id'        => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->errorProcessor($validator)], 403);
        }

        $user     = $this->authUser();
        $guestId  = $this->resolveGuestId($request, $user);

        if (!$user && !$guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']]
            ], 403);
        }

        $moduleId  = $this->getModuleId($request);
        // Multi-zone: clients send `zoneId: [3,1]` because a delivery point
        // can fall inside overlapping zones. Tools use whereIn() on the full
        // list. The conversation row keeps a single primary zone (first one)
        // so the per-zone conversation lookup stays a simple equality match.
        $zoneIds       = $this->getZoneIds($request);
        $primaryZoneId = $zoneIds[0] ?? null;
        $latitude  = $this->floatHeader($request, 'latitude');
        $longitude = $this->floatHeader($request, 'longitude');

        $conversation = $this->resolveConversation($request, $user, $guestId, $moduleId, $primaryZoneId);

        // Back-fill from the conversation when the client didn't echo the
        // module / zone headers on this turn. Without this, a follow-up
        // message ("which module am I in?") with stripped headers would
        // make AiChatService resolve to moduleType='general' and hit the
        // module-gate fallback even though the conversation row already
        // knows the module + zone.
        if (! $moduleId && $conversation->module_id) {
            $moduleId = (int) $conversation->module_id;
        }
        if (empty($zoneIds) && $conversation->zone_id) {
            $zoneIds = [(int) $conversation->zone_id];
        }

        $result = (new AiChatService($user, $moduleId, $zoneIds, $guestId, $latitude, $longitude))
            ->chat($conversation, $request->input('message'));

        return response()->json([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $result['message'],
            'tool_name'       => $result['tool_name'],
            'metadata'        => [
                'stores'          => $result['stores'],
                'products'        => $result['products'],
                'categories'      => $result['categories'],
                'cart_items'      => $result['cart_items'],
                'cart_updated'    => $result['cart_updated'],
            ],
         
        ], 200);
    }

    /**
     * List AI conversations for the authenticated user.
     *
     * GET /api/v1/customer/ai-chat/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user    = $this->authUser();
        $guestId = $this->resolveGuestId($request, $user);

        if (!$user && !$guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']]
            ], 403);
        }

        // Always return only the 5 most recent conversations
        $conversations = AiConversation::where('status', 'active')
            ->when($user, fn($q) => $q->where('user_id', $user->getKey()))
            ->when(!$user && $guestId, fn($q) => $q->where('guest_id', $guestId))
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'total_size' => $conversations->count(),
            'data'       => $conversations,
        ], 200);
    }

    /**
     * Get messages for a specific conversation.
     *
     * GET /api/v1/customer/ai-chat/messages?conversation_id=xxx
     */
    public function messages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|integer',
            'limit'           => 'nullable|integer|min:1|max:100',
            'offset'          => 'nullable|integer|min:1',
            'guest_id'        => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->errorProcessor($validator)], 403);
        }

        $user    = $this->authUser();
        $guestId = $this->resolveGuestId($request, $user);

        if (!$user && !$guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']]
            ], 403);
        }

        $conversation = $this->findConversation((int) $request->input('conversation_id'), $user, $guestId);

        if (! $conversation) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Conversation not found.']]], 404);
        }

        $limit  = (int) $request->input('limit', 50);
        $offset = (int) $request->input('offset', 1);

        $messages = AiMessage::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->paginate($limit, ['*'], 'page', $offset);

        $data = collect($messages->items())
            ->each(fn (AiMessage $message) => $message->metadata = $this->withGroupedCart($message->metadata))
            ->all();

        return response()->json([
            'total_size'      => $messages->total(),
            'limit'           => $limit,
            'offset'          => $offset,
            'conversation_id' => $conversation->id,
            'data'            => $data,
        ], 200);
    }

    /**
     * Archive (soft-delete) a conversation.
     *
     * DELETE /api/v1/customer/ai-chat/conversations/{id}
     */
    public function deleteConversation(Request $request, int $id): JsonResponse
    {
        $user    = $this->authUser();
        $guestId = $this->resolveGuestId($request, $user);

        if (!$user && !$guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']]
            ], 403);
        }

        $conversation = $this->findConversation($id, $user, $guestId);

        if (! $conversation) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Conversation not found.']]], 404);
        }

        $conversation->update(['status' => 'archived']);

        return response()->json(['message' => 'Conversation archived successfully.'], 200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authUser(): ?User
    {
        /** @var User|null $user */
        $user = auth('api')->user();
        return $user;
    }

    /**
     * Resolve guest identifier from request body (POST) or query string (GET).
     * Follows 6amMart system convention used in CartController, OrderController, etc.
     * Returns null when an authenticated user is present (user_id takes precedence).
     */
    private function resolveGuestId(Request $request, ?User $user): ?string
    {
        if ($user) {
            return null;
        }
        $guestId = $request->input('guest_id');
        return $guestId ? (string) $guestId : null;
    }

    private function resolveConversation(Request $request, ?User $user, ?string $guestId, ?int $moduleId, ?int $zoneId): AiConversation
    {
        $conversationId = (int) $request->input('conversation_id');

        if ($conversationId) {
            $existing = $this->findConversation($conversationId, $user, $guestId);
            if ($existing && $existing->status === 'active') {
                return $existing;
            }
        }

        // No (valid) conversation_id → always start a fresh conversation. The
        // client owns conversation threading: every response returns a
        // conversation_id, and sending it back continues that chat; omitting it
        // begins a new one. (Previously this auto-resumed the most recent active
        // conversation in the same module+zone within 30 minutes, which meant a
        // brand-new chat silently reattached to the old one — removed.)
        return AiConversation::create([
            'user_id'   => $user?->getKey(),
            'guest_id'  => $user ? null : $guestId,
            'module_id' => $moduleId,
            'zone_id'   => $zoneId,
            'status'    => 'active',
        ]);
    }

    private function findConversation(int $id, ?User $user, ?string $guestId): ?AiConversation
    {
        if (!$user && !$guestId) {
            return null;
        }

        return AiConversation::where('id', $id)
            ->when($user, fn($q) => $q->where('user_id', $user->getKey()))
            ->when(!$user && $guestId, fn($q) => $q->where('guest_id', $guestId))
            ->first();
    }

    private function getModuleId(Request $request): ?int
    {
        $id = $request->header('moduleId') ?? $request->input('module_id');
        return $id ? (int) $id : null;
    }

    private function floatHeader(Request $request, string $name): ?float
    {
        $value = $request->header($name) ?? $request->input($name);
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Resolve the zoneId header/body into an array of int zone IDs.
     *
     * Clients send `zoneId: [3,1]` (JSON array) because a delivery point can
     * sit inside overlapping zones. We pass the whole array through to the
     * tools so they can use `whereIn('zone_id', $zoneIds)`. A bare integer
     * is also accepted for back-compat.
     *
     * @return int[]
     */
    private function getZoneIds(Request $request): array
    {
        $raw = $request->header('zoneId') ?? $request->input('zone_id');

        if (! $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static fn ($v) => (int) $v, $decoded), static fn ($v) => $v > 0));
        }
        if ((int) $raw > 0) {
            return [(int) $raw];
        }
        return [];
    }

    /**
     * @deprecated kept temporarily so any old call sites don't fatal —
     * prefer getZoneIds() which preserves the full multi-zone list.
     */
    private function getZoneId(Request $request): ?int
    {
        $raw = $request->header('zoneId') ?? $request->input('zone_id');

        if ($raw) {
            // zoneId header is a JSON array e.g. [1,2,3] — use the first zone
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && ! empty($decoded)) {
                return (int) $decoded[0];
            }
            if ((int) $raw > 0) {
                return (int) $raw;
            }
        }

        // No zone in request — fall back to platform default, then first active zone.
        $default = Zone::where('status', 1)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        return $default ? (int) $default : null;
    }

    private function withGroupedCart(mixed $metadata): mixed
    {
        if (! is_array($metadata)) {
            return $metadata;
        }

        $cartItems = $metadata['cart_items'] ?? [];
        unset($metadata['cart_items']);

        $metadata['cart'] = $this->groupCartByStore(is_array($cartItems) ? $cartItems : []);

        return $metadata;
    }

    private function groupCartByStore(array $cartItems): array
    {
        $stores     = [];
        $grandTotal = 0.0;
        $totalItems = 0;

        foreach ($cartItems as $row) {
            if (! is_array($row)) {
                continue;
            }

            $storeId   = (int) ($row['store_id'] ?? 0);
            $lineTotal = round((float) ($row['line_total'] ?? 0), 2);
            $quantity  = (int) ($row['quantity'] ?? 0);

            if (! isset($stores[$storeId])) {
                $stores[$storeId] = [
                    'store_id'       => $storeId,
                    'store_name'     => $row['store_name'] ?? ('Store #' . $storeId),
                    'items'          => [],
                    'store_subtotal' => 0.0,
                ];
            }

            $stores[$storeId]['items'][] = [
                'cart_id'        => $row['cart_id'] ?? null,
                'item_id'        => (int) ($row['item_id'] ?? 0),
                'name'           => $row['name'] ?? null,
                'variation'      => $row['variation'] ?? '',
                'image'          => $row['image'] ?? null,
                'image_full_url' => $row['image_full_url'] ?? null,
                'quantity'       => $quantity,
                'unit_price'     => (float) ($row['unit_price'] ?? 0),
                'line_total'     => $lineTotal,
            ];
            $stores[$storeId]['store_subtotal'] = round($stores[$storeId]['store_subtotal'] + $lineTotal, 2);

            $grandTotal += $lineTotal;
            $totalItems++;
        }

        return [
            'stores'      => array_values($stores),
            'grand_total' => round($grandTotal, 2),
            'total_items' => $totalItems,
        ];
    }

    private function errorProcessor($validator): array
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = ['code' => $field, 'message' => $message];
            }
        }
        return $errors;
    }
}
