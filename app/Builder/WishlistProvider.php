<?php

namespace App\Builder;

use App\Models\Wishlist;
use Illuminate\Validation\ValidationException;
use Modules\Builder\Contracts\WishlistProvider as WishlistProviderContract;
use Modules\Builder\Services\StorefrontContext;

class WishlistProvider implements WishlistProviderContract
{
    public function __construct(private StorefrontContext $context)
    {
    }

    public function toggle(int $itemId): array
    {
        $userId = $this->requireUserId();

        $existing = Wishlist::query()
            ->where('user_id', $userId)
            ->where('item_id', $itemId)
            ->whereNull('store_id')
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            Wishlist::create([
                'user_id'  => $userId,
                'item_id'  => $itemId,
                'store_id' => null,
            ]);
            $inWishlist = true;
        }

        return [
            'inWishlist' => $inWishlist,
            'count'      => $this->countFor($userId),
        ];
    }

    public function count(): int
    {
        $userId = $this->context->getUserId();
        return $userId ? $this->countFor($userId) : 0;
    }

    public function has(int $itemId): bool
    {
        $userId = $this->context->getUserId();
        if (!$userId) {
            return false;
        }
        return Wishlist::query()
            ->where('user_id', $userId)
            ->where('item_id', $itemId)
            ->whereNull('store_id')
            ->exists();
    }

    private function countFor(int $userId): int
    {
        return Wishlist::query()
            ->where('user_id', $userId)
            ->whereNotNull('item_id')
            ->count();
    }

    private function requireUserId(): int
    {
        $userId = $this->context->getUserId();
        if (!$userId) {
            throw ValidationException::withMessages([
                '_form' => __('Sign in required'),
            ]);
        }
        return $userId;
    }
}
