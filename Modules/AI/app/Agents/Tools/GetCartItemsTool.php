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

class GetCartItemsTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?User             $user     = null,
        private readonly ?int              $moduleId = null,
        private readonly ?string           $guestId  = null,
    ) {}

    public function description(): string
    {
        return 'Show the customer\'s current cart contents — items grouped by store, with quantities, prices, and per-store + overall totals. Carts can hold items from multiple stores (the customer checks them out store-by-store), so always present the breakdown that way. Works for both authenticated users and guests. Use when the customer asks "show my cart", "what\'s in my cart", "view cart", or similar.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetCartItemsTool');

        if (! $this->user && ! $this->guestId) {
            return 'Cannot show cart: no customer identity available in this chat session.';
        }

        $isGuest    = $this->user === null;
        $cartUserId = $isGuest ? $this->guestId : $this->user->getKey();

        $query = Cart::where('user_id', $cartUserId)
            ->where('is_guest', $isGuest)
            ->where('item_type', Item::class)
            ->with('item:id,name,price,discount,discount_type,store_id,image');

        if ($this->moduleId) {
            $query->where('module_id', $this->moduleId);
        }

        $carts = $query->get();

        if ($carts->isEmpty()) {
            return 'Cart is empty.';
        }

        // Resolve store names in one query for the per-bucket display
        // (matches CartController::get_all_carts grouping shape).
        $storeIds   = $carts->pluck('store_id')->filter()->unique()->values()->all();
        $storesById = $storeIds
            ? Store::whereIn('id', $storeIds)->get(['id', 'name', 'logo'])->keyBy('id')
            : collect();

        // Group by store_id. Falls back to item.store_id if the cart row
        // pre-dates the store_id column (rows written before the host
        // migration on 2026-05-13 won't have it set).
        $grouped = $carts->groupBy(fn ($cart) => $cart->getAttribute('store_id')
            ?? data_get($cart, 'item.store_id')
            ?? 0);

        $cartData      = [];
        $bucketSummaries = [];
        $grandTotal    = 0.0;
        $itemCount     = 0;

        foreach ($grouped as $storeId => $rows) {
            $store     = $storesById->get((int) $storeId);
            $storeName = $store?->name ?? 'Store #' . (int) $storeId;
            $bucketTotal = 0.0;
            $bucketLines = [];

            foreach ($rows as $cart) {
                /** @var Item|null $item */
                $item      = $cart->item;
                $name      = $item ? $item->getAttribute('name') : 'Unknown item';
                $qty       = (int) $cart->getAttribute('quantity');
                $unitPrice = (float) $cart->getAttribute('price');

                $discountedUnit = $unitPrice;
                if ($item) {
                    $discount     = (float) $item->getAttribute('discount');
                    $discountType = (string) $item->getAttribute('discount_type');
                    if ($discount > 0) {
                        $discountedUnit = $discountType === 'percent'
                            ? round($unitPrice - $unitPrice * $discount / 100, 2)
                            : round($unitPrice - $discount, 2);
                    }
                }

                $lineTotal    = round($discountedUnit * $qty, 2);
                $bucketTotal += $lineTotal;
                $itemCount++;

                $variationText = $this->cartVariationLabel($cart->getAttribute('variation'));
                $variationTag  = $variationText !== '' ? ' (' . $variationText . ')' : '';

                $bucketLines[] = $name . $variationTag . ' [ID:' . $cart->getAttribute('item_id') . '] x' . $qty . ' = ' . $lineTotal;
                $cartData[]    = [
                    'cart_id'        => $cart->getKey(),
                    'item_id'        => (int) $cart->getAttribute('item_id'),
                    'store_id'       => (int) $storeId,
                    'store_name'     => $storeName,
                    'name'           => $name,
                    'variation'      => $variationText,
                    'image'          => $item?->getAttribute('image'),
                    'image_full_url' => $item?->image_full_url,
                    'quantity'       => $qty,
                    'unit_price'     => $discountedUnit,
                    'line_total'     => $lineTotal,
                ];
            }

            $grandTotal       += $bucketTotal;
            $bucketSummaries[] = '"' . $storeName . '": ' . implode('; ', $bucketLines)
                . ' [store subtotal: ' . round($bucketTotal, 2) . ']';
        }

        $this->context->addCartItems($cartData);

        $bucketCount = $grouped->count();
        $storeWord   = $bucketCount === 1 ? 'store' : 'stores';
        return $itemCount . ' item(s) across ' . $bucketCount . ' ' . $storeWord . ' — '
            . implode(' | ', $bucketSummaries)
            . '. Grand total: ' . round($grandTotal, 2)
            . '. Note: each store is checked out separately.';
    }

    /**
     * Human-readable label for a stored cart `variation` value (food:
     * [{name, values:{label:[...]}}], non-food: [{type:"..."}]). Peels up to two
     * json-encode layers because cart rows are written pre-encoded by convention.
     */
    private function cartVariationLabel(mixed $raw): string
    {
        for ($i = 0; $i < 2 && is_string($raw); $i++) {
            $decoded = json_decode($raw, true);
            if ($decoded === null) {
                break;
            }
            $raw = $decoded;
        }
        if (! is_array($raw) || empty($raw)) {
            return '';
        }

        $parts = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (isset($entry['values']['label'])) {
                $labels = array_filter(array_map('strval', (array) $entry['values']['label']));
                if (! empty($labels)) {
                    $parts[] = implode(', ', $labels);
                }
            } elseif (isset($entry['type']) && $entry['type'] !== '') {
                $parts[] = (string) $entry['type'];
            }
        }

        return implode(' • ', $parts);
    }
}
