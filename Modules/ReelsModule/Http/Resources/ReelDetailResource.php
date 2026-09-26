<?php

namespace Modules\ReelsModule\Http\Resources;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReelDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $videoDisk = optional($this->storage->firstWhere('key', 'video'))->value ?? 'public';

        $videoUrl = $videoDisk === 'public'
            ? route('customer.reels.show', ['reel_id' => $this->id, 'stream' => 1])
            : $this->video_full_url;

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
            'id' => $this->id,
            'description' => $this->description,
            'product_type' => $isItem ? 'item' : ($isVehicle ? 'vehicle' : ($isService ? 'service' : ($productType ? \Illuminate\Support\Str::snake(class_basename($productType)) : null))),
            'product_id' => $this->productable_id,
            'item_id' => $isItem ? $this->productable_id : null,
            'vehicle_id' => $isVehicle ? $this->productable_id : null,
            'service_id' => $isService ? $this->productable_id : null,
            'order_now_button' => (bool) $this->order_now_button && $orderable,
            'video_url' => $videoUrl,
            'thumbnail_url' => $this->thumbnail_full_url,
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
                'logo_full_url' => $this->store?->logo_full_url,
                'verified_seller' => Helpers::get_verified_seller_status($this->store, $this->store?->storeConfig),
            ],
            'item' => ($isItem && $product) ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image_full_url' => $product->image_full_url,
                'store_id' => $product->store_id,
                'stock' => $product->stock,
                'maximum_cart_quantity' => $product->maximum_cart_quantity,
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
            'total_views' => (int) $this->total_views,
            'total_likes' => (int) $this->total_likes,
            'total_store_visits' => (int) $this->total_store_visits,
            'total_sale' => (int) $this->order_count,
            'total_sale_amount' => (float) $this->total_sale_amount,
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->map(fn ($translation) => [
                'id' => $translation->id,
                'key' => $translation->key,
                'value' => $translation->value,
                'locale' => $translation->locale,
            ])->values(), []),
        ];
    }
}
