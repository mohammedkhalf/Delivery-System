<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveFromCartTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user     = null,
        private readonly ?int              $moduleId = null,
        private readonly ?string           $guestId  = null,
    ) {}

    public function description(): string
    {
        return 'Remove an item from the customer\'s cart, or clear an entire store bucket / the whole cart. Carts are grouped per-store (multi-store is allowed); when the customer says "clear my cart" without naming a store, the default is to clear ALL stores. Pass store_id to scope a clear to a single store bucket. Works for both authenticated users and guests.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id'    => $schema->number()
                ->description('ID of the item to remove. Pass null to clear the entire cart or a store bucket.')
                ->required()
                ->nullable(),
            'item_name'  => $schema->string()
                ->description('OPTIONAL fallback — the item name the user referred to. If item_id doesn\'t resolve to a cart row, the tool looks up the cart by this name. Always pass it when you know the name.')
                ->required()
                ->nullable(),
            'clear_all'  => $schema->boolean()
                ->description('true to remove items from the cart in bulk. Combine with store_id to clear a single store bucket; without store_id this clears the whole cart. Requires explicit user confirmation intent.')
                ->required()
                ->nullable(),
            'store_id'   => $schema->number()
                ->description('OPTIONAL — scope the removal to a single store bucket. Use this when the customer says "clear my <Store Name> cart" or removes the last item from a specific store. Pass null to act across all stores.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('RemoveFromCartTool');

        if (! $this->user && ! $this->guestId) {
            return 'Cannot modify cart: no customer identity available in this chat session.';
        }

        $args       = $request->all();
        $itemId     = ($args['item_id'] ?? null) !== null ? (int) $args['item_id'] : null;
        $itemName   = isset($args['item_name']) && $args['item_name'] !== null
            ? trim((string) $args['item_name'])
            : null;
        $clearAll   = ($args['clear_all'] ?? null) !== null ? (bool) $args['clear_all'] : false;
        $storeId    = ($args['store_id'] ?? null) !== null ? (int) $args['store_id'] : null;
        $isGuest    = $this->user === null;
        $cartUserId = $isGuest ? $this->guestId : $this->user->getKey();

        $baseQuery = fn () => Cart::where('user_id', $cartUserId)
            ->where('is_guest', $isGuest)
            ->where('item_type', Item::class)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId));

        // Clear entire cart, or a single store bucket if store_id was passed
        // (mirrors host's CartController::remove_cart which is store-scoped).
        if ($clearAll) {
            $deleted = $baseQuery()->delete();
            $this->publishCartSnapshot($cartUserId, $isGuest);
            $scope = $storeId ? "store #{$storeId} bucket" : 'cart';
            return $deleted > 0
                ? "Your {$scope} has been cleared ({$deleted} item(s) removed)."
                : "Your {$scope} was already empty.";
        }

        if (! $itemId && ($itemName === null || $itemName === '')) {
            return 'Please specify which item to remove (item ID or name), or ask to clear the entire cart.';
        }

        $cart = null;
        if ($itemId) {
            $cart = $baseQuery()->where('item_id', $itemId)->first();
        }

        if (! $cart && $itemName !== null && $itemName !== '') {
            $candidateIds = Item::where('name', 'like', "%{$itemName}%")->pluck('id')->all();
            if (! empty($candidateIds)) {
                $cart = $baseQuery()->whereIn('item_id', $candidateIds)->first();
            }
            if ($cart) {
                $itemId = (int) $cart->getAttribute('item_id');
            }
        }

        if (! $cart) {
            $label = $itemName ?: ('Item [ID:' . $itemId . ']');
            return "{$label} is not in your cart.";
        }

        $name = $cart->item?->getAttribute('name') ?? "Item #{$itemId}";
        $cart->delete();
        $this->publishCartSnapshot($cartUserId, $isGuest);

        return "{$name} has been removed from your cart.";
    }

    /**
     * Re-read the cart after a mutation and publish a fresh snapshot to the
     * response context so the API response carries the post-mutation state.
     */
    private function publishCartSnapshot(int|string $cartUserId, bool $isGuest): void
    {
        $carts = Cart::where('user_id', $cartUserId)
            ->where('is_guest', $isGuest)
            ->where('item_type', Item::class)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->with('item:id,name,price,discount,discount_type,store_id,image')
            ->get();

        $storeIds   = $carts->pluck('store_id')->filter()->unique()->values()->all();
        $storesById = $storeIds
            ? Store::whereIn('id', $storeIds)->get(['id', 'name'])->keyBy('id')
            : collect();

        $data = [];
        foreach ($carts as $row) {
            /** @var Item|null $itm */
            $itm  = $row->item;
            $qty  = (int) $row->getAttribute('quantity');
            $unit = (float) $row->getAttribute('price');
            if ($itm) {
                $disc     = (float) $itm->getAttribute('discount');
                $discType = (string) $itm->getAttribute('discount_type');
                if ($disc > 0) {
                    $unit = $discType === 'percent'
                        ? round($unit - $unit * $disc / 100, 2)
                        : round($unit - $disc, 2);
                }
            }
            $sid = (int) ($row->getAttribute('store_id') ?? $itm?->getAttribute('store_id') ?? 0);
            $data[] = [
                'cart_id'        => $row->getKey(),
                'item_id'        => (int) $row->getAttribute('item_id'),
                'store_id'       => $sid,
                'store_name'     => $storesById->get($sid)?->name,
                'name'           => $itm?->getAttribute('name') ?? 'Unknown item',
                'image'          => $itm?->getAttribute('image'),
                'image_full_url' => $itm?->image_full_url,
                'quantity'       => $qty,
                'unit_price'     => $unit,
                'line_total'     => round($unit * $qty, 2),
            ];
        }
        $this->context->addCartItems($data);
    }
}
