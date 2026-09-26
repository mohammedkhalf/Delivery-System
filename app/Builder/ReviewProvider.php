<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\StoreLogic;
use App\Models\DeliveryMan;
use App\Models\DMReview;
use App\Models\Item;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Builder\Contracts\ReviewProvider as ReviewProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * Customer review submission against delivered orders.
 *
 * Mirrors the host's existing `Api\V1\ItemController::submit_product_review`
 * and `Api\V1\DeliveryManReviewController::submit_review` exactly — same
 * Review / DMReview models, same denorm chain (Item.rating + avg_rating +
 * rating_count; Store.rating with the host's reversed bucket order;
 * OrderReference.is_reviewed). Reviews submitted from the storefront
 * propagate to every read surface (item detail avg, store rating, mobile
 * review reminder) identically to mobile-side submissions.
 *
 * Key differences from host:
 *   - Ownership-checks the order (host's `Order::find()` accepts any id
 *     from any user — security gap we don't propagate).
 *   - Wraps the side-effect chain in DB::transaction so a Review insert
 *     plus partial denorm update can't leave the row set inconsistent.
 *   - Returns structured errors instead of 403/200 responses; controller
 *     translates to flash.
 */
class ReviewProvider implements ReviewProviderContract
{
    /* ─── reviewContext ─────────────────────────────────────── */

    public function reviewContext(?StorefrontScope $scope, int $orderId, int $customerId): ?array
    {
        $order = $this->loadOrder($scope, $orderId, $customerId);
        if (!$order) {
            return null;
        }

        // Pull already-reviewed item ids in a single query rather than
        // N+1 per detail. Same shape the host uses for the
        // app-level uniqueness check, just batched.
        $reviewedItemIds = Review::query()
            ->where('user_id', $customerId)
            ->where('order_id', $orderId)
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Slug lookup for already-reviewed entries so the frontend
        // could deep-link to "see your review" in a future iteration.
        $reviewSlugs = Review::query()
            ->where('user_id', $customerId)
            ->where('order_id', $orderId)
            ->pluck('review_id', 'item_id')
            ->all();

        // Dedupe details by item_id — an order can carry multiple
        // detail rows for the same item (different variants). The
        // Review uniqueness is (item_id, user_id, order_id), so one
        // review covers all variants. Showing one card per item is
        // less confusing than per-variant cards that all complete at
        // once.
        $itemsById = [];
        foreach ($order->details as $detail) {
            $itemId = (int) ($detail->item_id ?? 0);
            if ($itemId <= 0 || isset($itemsById[$itemId])) {
                continue;
            }
            $itemsById[$itemId] = $this->mapItemForReview($detail, $reviewedItemIds, $reviewSlugs);
        }
        $items = array_values($itemsById);

        $deliveryMan = null;
        if ($order->delivery_man) {
            $dmReviewed = DMReview::query()
                ->where('user_id', $customerId)
                ->where('order_id', $orderId)
                ->where('delivery_man_id', $order->delivery_man->id)
                ->exists();
            $deliveryMan = $this->mapDeliveryManForReview($order->delivery_man, $dmReviewed);
        }

        $allItemsReviewed = collect($items)->every(fn ($i) => $i['reviewed']);
        $dmDone = $deliveryMan === null || $deliveryMan['reviewed'];

        return [
            'orderId'     => (int) $order->id,
            'items'       => $items,
            'deliveryMan' => $deliveryMan,
            'allReviewed' => $allItemsReviewed && $dmDone,
        ];
    }

    /* ─── submitItemReview ──────────────────────────────────── */

    public function submitItemReview(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        int $itemId,
        int $rating,
        ?string $comment,
        array $imageFiles = [],
    ): array {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Rating must be between 1 and 5.'];
        }

        $order = $this->loadOrder($scope, $orderId, $customerId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        // Pre-check duplicate so the user sees a friendly message instead
        // of catching a DB-side error mid-transaction. The host has no
        // unique index — protection is app-level via this check.
        $existing = Review::query()
            ->where('item_id', $itemId)
            ->where('user_id', $customerId)
            ->where('order_id', $orderId)
            ->exists();
        if ($existing) {
            return ['success' => false, 'error' => 'You have already reviewed this item.'];
        }

        // Verify the item belongs to this order — prevents reviewing an
        // arbitrary item id with a delivered-order id from a different
        // store.
        $orderItemIds = $order->details->pluck('item_id')->map(fn ($v) => (int) $v)->all();
        if (!in_array($itemId, $orderItemIds, true)) {
            return ['success' => false, 'error' => 'This item is not part of the order.'];
        }

        $item = Item::query()->find($itemId);
        if (!$item) {
            return ['success' => false, 'error' => 'Item not found.'];
        }

        // Image upload BEFORE the transaction — uploads aren't transactional
        // anyway (they hit external storage) and we don't want to roll back
        // a DB write while leaving uploaded files orphaned. If any upload
        // fails we collect what succeeded; an empty result is fine (review
        // can submit without attachments).
        $attachmentPaths = $this->uploadAttachments($imageFiles, 'item review');

        try {
            $reviewId = DB::transaction(function () use ($order, $item, $customerId, $itemId, $orderId, $rating, $comment, $attachmentPaths) {
                $review = new Review();
                $review->user_id     = $customerId;
                $review->item_id     = $itemId;
                $review->order_id    = $orderId;
                $review->module_id   = $order->module_id;
                $review->store_id    = $order->store_id;
                $review->comment     = $comment !== null && $comment !== '' ? $comment : null;
                $review->rating      = $rating;
                $review->attachment  = json_encode($attachmentPaths);
                $review->save();
                // Note: Review::boot() `saved` hook generates `review_id`
                // slug and re-saves. By the time we read $review->review_id
                // below it's populated.

                // OrderReference.is_reviewed flag — used by the mobile
                // review reminder pipeline. Mirror the host's defensive
                // optional chain: legacy orders predating the observer
                // have no OrderReference row.
                if ($order->OrderReference) {
                    $order->OrderReference->update(['is_reviewed' => 1]);
                }

                // Store rating bucket — passes the EXISTING rating array
                // into StoreLogic which applies its own reversed-bucket
                // convention internally. Do NOT pre-decode or transpose.
                if ($item->store) {
                    $item->store->rating = StoreLogic::update_store_rating(
                        $item->store->rating,
                        (int) $rating,
                    );
                    $item->store->save();
                }

                // Item rating denorm — JSON bucket + weighted average +
                // count. Matches the host's chain verbatim.
                $item->rating     = ProductLogic::update_rating($item->rating, (int) $rating);
                $item->avg_rating = ProductLogic::get_avg_rating(json_decode($item->rating, true));
                $item->save();
                $item->increment('rating_count');

                return (string) ($review->review_id ?? '');
            });
        } catch (\Throwable $e) {
            Log::warning('Item review submit failed', [
                'order_id' => $orderId,
                'item_id'  => $itemId,
                'error'    => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Could not submit the review. Please try again.'];
        }

        return [
            'success'  => true,
            'message'  => 'Thanks for the review!',
            'reviewId' => $reviewId,
        ];
    }

    /* ─── submitDeliveryManReview ───────────────────────────── */

    public function submitDeliveryManReview(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        int $rating,
        string $comment,
        array $imageFiles = [],
    ): array {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Rating must be between 1 and 5.'];
        }
        $comment = trim($comment);
        if ($comment === '') {
            return ['success' => false, 'error' => 'Please share your opinion before submitting.'];
        }

        $order = $this->loadOrder($scope, $orderId, $customerId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }
        if (!$order->delivery_man_id || !$order->delivery_man) {
            return ['success' => false, 'error' => 'No delivery partner is assigned to this order.'];
        }

        $deliveryManId = (int) $order->delivery_man_id;

        $existing = DMReview::query()
            ->where('delivery_man_id', $deliveryManId)
            ->where('user_id', $customerId)
            ->where('order_id', $orderId)
            ->exists();
        if ($existing) {
            return ['success' => false, 'error' => 'You have already reviewed this delivery partner.'];
        }

        $attachmentPaths = $this->uploadAttachments($imageFiles, 'dm review');

        try {
            DB::transaction(function () use ($customerId, $deliveryManId, $orderId, $rating, $comment, $attachmentPaths) {
                $review = new DMReview();
                $review->user_id         = $customerId;
                $review->delivery_man_id = $deliveryManId;
                $review->order_id        = $orderId;
                $review->comment         = $comment;
                $review->rating          = $rating;
                $review->attachment      = json_encode($attachmentPaths);
                $review->save();
                // DM aggregate avg/count denorms aren't stored — DeliveryMan::rating()
                // is a HasMany with SQL aggregation. Next read picks up the new row.
            });
        } catch (\Throwable $e) {
            Log::warning('DM review submit failed', [
                'order_id'        => $orderId,
                'delivery_man_id' => $deliveryManId,
                'error'           => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Could not submit the review. Please try again.'];
        }

        return ['success' => true, 'message' => 'Thanks for the review!'];
    }

    /* ─── helpers ───────────────────────────────────────────── */

    /**
     * Order load with ownership + delivery + scope predicates. Returns
     * null for "ineligible" regardless of reason (not owned, not
     * delivered, scope mismatch) — caller surfaces a neutral message
     * so we don't leak which check failed.
     */
    private function loadOrder(?StorefrontScope $scope, int $orderId, int $customerId): ?Order
    {
        return Order::query()
            ->with(['details', 'delivery_man', 'OrderReference'])
            ->where('id', $orderId)
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->where('order_status', 'delivered')
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();
    }

    /**
     * Map an order detail row into the review-card shape. Falls back to
     * the order_details.item_details JSON snapshot when the live item
     * has been deleted from the catalog — the customer still gets a
     * card to review against the historical record.
     */
    private function mapItemForReview($detail, array $reviewedItemIds, array $reviewSlugs): array
    {
        $itemId = (int) ($detail->item_id ?? 0);
        $snapshot = is_string($detail->item_details ?? null)
            ? (json_decode($detail->item_details, true) ?: [])
            : (is_array($detail->item_details ?? null) ? $detail->item_details : []);

        $name = $snapshot['name'] ?? ($detail->item->name ?? 'Item');
        $image = null;
        try {
            $image = $detail->item->image_full_url ?? ($snapshot['image_full_url'] ?? null);
        } catch (\Throwable) {
            $image = $snapshot['image_full_url'] ?? null;
        }

        $reviewed = in_array($itemId, $reviewedItemIds, true);
        return [
            'id'        => $itemId,
            'name'      => (string) $name,
            'image'     => $image ?: null,
            'qty'       => (int) ($detail->quantity ?? 0),
            'unitPrice' => (float) ($detail->price ?? 0),
            'reviewed'  => $reviewed,
            'reviewId'  => $reviewed ? ($reviewSlugs[$itemId] ?? null) : null,
        ];
    }

    private function mapDeliveryManForReview(DeliveryMan $dm, bool $reviewed): array
    {
        // Live aggregation via DeliveryMan::rating() — same path the
        // order-tracking DM card uses. relationLoaded check avoids a
        // second query when the caller eager-loaded the relation.
        $ratingRow = $dm->relationLoaded('rating')
            ? $dm->getRelation('rating')->first()
            : $dm->rating()->first();
        $average = (float) ($ratingRow->average ?? 0);
        $count   = (int)   ($ratingRow->rating_count ?? 0);

        $name = trim(((string) ($dm->f_name ?? '')) . ' ' . ((string) ($dm->l_name ?? '')));
        if ($name === '') $name = 'Delivery Partner';

        return [
            'id'          => (int) $dm->id,
            'name'        => $name,
            'image'       => $this->safeImage($dm),
            'avgRating'   => round($average, 1),
            'ratingCount' => $count,
            'reviewed'    => $reviewed,
        ];
    }

    private function safeImage($model): ?string
    {
        try {
            return $model->image_full_url ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Upload each file via the host's `Helpers::upload` — same helper
     * that powers the refund + inbox + cart-add image flows. Per-file
     * try/catch so a single corrupt file doesn't drop the entire
     * review. The 'png' extension hint is overridden inside the
     * helper by the file's actual extension.
     */
    private function uploadAttachments(array $imageFiles, string $logContext): array
    {
        $paths = [];
        foreach ($imageFiles as $file) {
            if (!$file) continue;
            try {
                $name = Helpers::upload('review/', 'png', $file);
                // Mirror the host's shape — flat array of relative
                // paths is what `submit_product_review` writes. Some
                // host read paths expect `['img' => …, 'storage' => …]`
                // (refund), but Review.attachment uses flat strings.
                // Keep parity with `submit_product_review`'s storage.
                $paths[] = $name;
            } catch (\Throwable $e) {
                Log::warning("{$logContext} attachment upload failed", [
                    'file_name' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
        return $paths;
    }
}
