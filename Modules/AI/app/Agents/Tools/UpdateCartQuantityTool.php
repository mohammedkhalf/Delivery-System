<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\CentralLogics\Helpers;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateCartQuantityTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user       = null,
        private readonly ?int              $moduleId   = null,
        private readonly ?string           $guestId    = null,
        private readonly string            $moduleType = 'general',
    ) {}

    public function description(): string
    {
        return 'Update the quantity of an existing item in the customer\'s cart. Carts can contain items from multiple stores (the storefront treats each store as its own bucket); pass store_id when the same item could live in more than one bucket. Works for both authenticated users and guests. Requires the item_id.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id'        => $schema->number()
                ->description('ID of the cart item to update.')
                ->required(),
            'item_name'      => $schema->string()
                ->description('OPTIONAL fallback — the item name the user referred to. If item_id doesn\'t resolve to a cart row, the tool looks up the cart by this name. Always pass it when you know the name.')
                ->required()
                ->nullable(),
            'quantity'       => $schema->number()
                ->description('New absolute quantity to set. Must be >= 1. NOTE: this is the FINAL quantity, not a delta — for "add 5 more" pass current_quantity + 5.')
                ->required(),
            'variation_type' => $schema->string()
                ->description('Variation type string (e.g. "250ml") to identify the correct cart row when the item has variations. Pass null if not applicable.')
                ->required()
                ->nullable(),
            'store_id'       => $schema->number()
                ->description('OPTIONAL — scope the lookup to a specific store bucket when the same item could exist in multiple buckets. Pass null otherwise.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('UpdateCartQuantityTool');

        if (! $this->user && ! $this->guestId) {
            return 'Cannot update cart: no customer identity available in this chat session.';
        }

        $args          = $request->all();
        $itemId        = (int) ($args['item_id'] ?? 0);
        $itemName      = isset($args['item_name']) && $args['item_name'] !== null
            ? trim((string) $args['item_name'])
            : null;
        $quantity      = (int) ($args['quantity'] ?? 0);
        $variationType = isset($args['variation_type']) && $args['variation_type'] !== null
            ? trim((string) $args['variation_type'])
            : null;
        $storeId       = ($args['store_id'] ?? null) !== null ? (int) $args['store_id'] : null;

        if ($itemId <= 0 && ($itemName === null || $itemName === '')) {
            return 'Please specify which item to update (item ID or name is required).';
        }

        if ($quantity < 1) {
            return 'Quantity must be at least 1. To remove the item, use the remove from cart option.';
        }

        $isGuest    = $this->user === null;
        $cartUserId = $isGuest ? $this->guestId : $this->user->getKey();

        $baseQuery = fn () => Cart::where('user_id', $cartUserId)
            ->where('is_guest', $isGuest)
            ->where('item_type', Item::class)
            ->when($this->moduleId, fn ($q) => $q->where('module_id', $this->moduleId))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId));

        // Primary lookup by item_id (+ optional variation)
        $cart = null;
        if ($itemId > 0) {
            $cart = $baseQuery()
                ->where('item_id', $itemId)
                ->when($variationType !== null && $variationType !== '', fn ($q) => $q->where('variation', 'like', '%' . $variationType . '%'))
                ->first();
        }

        // Fallback: resolve by item name. Cart::item is a morphTo (polymorphic),
        // so we resolve Item IDs first then filter cart rows by them.
        if (! $cart && $itemName !== null && $itemName !== '') {
            $candidateIds = Item::where('name', 'like', "%{$itemName}%")
                ->pluck('id')
                ->all();

            if (! empty($candidateIds)) {
                $cart = $baseQuery()
                    ->whereIn('item_id', $candidateIds)
                    ->when($variationType !== null && $variationType !== '', fn ($q) => $q->where('variation', 'like', '%' . $variationType . '%'))
                    ->first();
            }

            if ($cart) {
                $itemId = (int) $cart->getAttribute('item_id');
            }
        }

        if (! $cart) {
            $variationHint = $variationType ? " ({$variationType})" : '';
            $label         = $itemName ?: ('Item #' . $itemId);
            return "{$label}{$variationHint} is not in your cart. Add it first before updating the quantity.";
        }

        // Enforce stock + max-cart-quantity the same way AddToCartTool /
        // PlaceNewOrder do: read variation stock via Helpers::variation_price,
        // and only enforce stock for modules whose config('module.X.stock')
        // flag is true (grocery, pharmacy, e-commerce).
        $item = Item::find($itemId, ['id', 'name', 'maximum_cart_quantity', 'stock', 'variations']);

        if ($item) {
            $rawStock = $item->getAttribute('stock');
            // Legacy per-variant stock lookup only applies to the non-food
            // variation system (variations = [{type, price, stock}]). Food uses
            // food_variations with item-level stock, so skip the helper there —
            // calling it would iterate a null `variations` column.
            if ($this->moduleType !== 'food' && $variationType !== null && $variationType !== '' && !empty($item->getAttribute('variations'))) {
                $variantData = Helpers::variation_price($item, json_encode([['type' => $variationType]]));
                $rawStock    = $variantData['stock'] ?? $rawStock;
            }

            $tracksStock = (bool) (config('module.' . $this->moduleType . '.stock') ?? false);
            $stock       = $tracksStock
                ? (is_numeric($rawStock) ? (int) $rawStock : 0)
                : -1;

            $maxQty = (int) $item->getAttribute('maximum_cart_quantity');
            $name   = $item->getAttribute('name');
            $vlabel = $variationType ? " ({$variationType})" : '';

            if ($tracksStock && $stock === 0) {
                return "\"{$name}\"{$vlabel} is currently out of stock. Quantity not updated.";
            }

            if ($stock > 0 && $quantity > $stock) {
                return "Only {$stock} unit(s) of \"{$name}\"{$vlabel} are available. Quantity not updated.";
            }

            if ($maxQty > 0 && $quantity > $maxQty) {
                return "Maximum allowed quantity per cart for \"{$name}\" is {$maxQty}. Quantity not updated.";
            }
        } else {
            $name = "Item #{$itemId}";
        }

        $cart->update(['quantity' => $quantity]);
        $this->publishCartSnapshot($cartUserId, $isGuest);

        $variationLabel = $variationType ? " ({$variationType})" : '';
        return "Updated {$name}{$variationLabel} quantity to {$quantity} in your cart.";
    }

    /**
     * Re-read the cart and push a fresh snapshot into the response context so
     * the API response carries the post-mutation state instead of the stale
     * snapshot from any earlier GetCartItemsTool call this turn.
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
