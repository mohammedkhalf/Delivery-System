<?php

namespace Modules\ReelsModule\Http\Resources;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReelListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productType = $this->productable_type;
        $rawProduct = $this->productable;
        $orderable = $rawProduct
            && (int) ($rawProduct->status ?? 1) === 1
            && (!isset($rawProduct->is_approved) || (int) $rawProduct->is_approved === 1);
        $product = $orderable ? $rawProduct : null;
        $isItem = $productType === \App\Models\Item::class;
        $isVehicle = $productType === \Modules\Rental\Entities\Vehicle::class;
        $isService = $productType === \Modules\Service\Entities\Service::class;

        return [
            'reel_id' => $this->id,
            'description' => $this->description,
            'thumbnail_full_url' => $this->thumbnail_full_url,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'store_logo_full_url' => $this->store?->logo_full_url,
            'product_type' => $isItem ? 'item' : ($isVehicle ? 'vehicle' : ($isService ? 'service' : ($productType ? \Illuminate\Support\Str::snake(class_basename($productType)) : null))),
            'product_id' => $this->productable_id,
            'item_id' => $isItem ? $this->productable_id : null,
            'vehicle_id' => $isVehicle ? $this->productable_id : null,
            'service_id' => $isService ? $this->productable_id : null,
            'order_now_button' => (bool) $this->order_now_button && $orderable,
            'item' => ($isItem && $product) ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image_full_url' => $product->image_full_url,
                'store_id' => $product->store_id,
            ] : null,
            'service' => ($isService && $product) ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->base_price,
                'image_full_url' => $product->thumbnail_full_url,
                'store_id' => $product->store_id,
            ] : null,
            'vehicle' => ($isVehicle && $product) ? [
                'id' => $product->id,
                'name' => $product->name,
                'thumbnail_full_url' => $product->thumbnail_full_url,
                'hourly_price' => (float) $product->hourly_price,
                'day_wise_price' => (float) $product->day_wise_price,
                'distance_price' => (float) $product->distance_price,
                'provider_id' => $product->provider_id,
            ] : null,
            'verified_seller' => Helpers::get_verified_seller_status($this->store, $this->store?->storeConfig),
            'stats' => (new ReelStatsResource($this->resource))->resolve(),
        ];
    }
}
