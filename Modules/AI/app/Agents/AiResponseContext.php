<?php

namespace Modules\AI\app\Agents;

/**
 * Request-scoped context that tools write structured data into.
 * The service reads it after the agent prompt completes to include
 * product/store cards in the API response and to log which tools ran.
 */
class AiResponseContext
{
    private array $products     = [];
    private array $stores       = [];
    private array $categories   = [];
    private array $cartItems    = [];
    private array $toolsInvoked = [];

    public function addProducts(array $products): void
    {
        $this->products = array_merge($this->products, $products);
    }

    public function addStores(array $stores): void
    {
        $this->stores = array_merge($this->stores, $stores);
    }

    public function addCategories(array $categories): void
    {
        $this->categories = array_merge($this->categories, $categories);
    }

    public function addCartItems(array $items): void
    {
        $this->cartItems = $items;
    }

    public function recordTool(string $toolName): void
    {
        if (! in_array($toolName, $this->toolsInvoked, true)) {
            $this->toolsInvoked[] = $toolName;
        }
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function getStores(): array
    {
        return $this->stores;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    /** Returns the unique names of every tool that was called during this turn. */
    public function getToolsInvoked(): array
    {
        return $this->toolsInvoked;
    }

    public function reset(): void
    {
        $this->products     = [];
        $this->stores       = [];
        $this->categories   = [];
        $this->cartItems    = [];
        $this->toolsInvoked = [];
    }
}
