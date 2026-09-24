<?php

namespace App\Domain\Ordering;

use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\OrderItemFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Allocates a cart's lines across stores by distance and stock, and places the order.
 * Genuinely multi-step and cutting across Store/Product/Order — the one class in this
 * codebase that earns an exception to "no service layer".
 *
 * Stock-shortfall policy (locked decision, Blinkit/Instamart pattern): a line that can't be
 * fully covered is capped to what's available rather than rejecting the whole order. The
 * Checkout Livewire component calls preview() first so the customer can see and confirm any
 * adjustment before place() commits it.
 */
class OrderPlacer
{
    public function __construct(protected DiscountEvaluator $discountEvaluator) {}

    public function preview(Cart $cart, ?Address $address): CheckoutPreview
    {
        $items = $this->availableItems($cart);
        $currency = $this->currency();

        $previewLines = [];
        $cartLines = [];

        foreach ($items as $item) {
            $candidates = $address && $address->hasCoordinates()
                ? Store::query()->nearestTo((float) $address->lat, (float) $address->lng, $item->product_id)->get()
                : collect();

            [$allocations, $fulfillable] = $this->distribute($item->quantity, $candidates);

            $previewLines[] = new PreviewLine(
                productId: $item->product_id,
                productName: $item->product->name,
                requestedQuantity: $item->quantity,
                fulfillableQuantity: $fulfillable,
                allocations: $allocations,
            );

            $cartLines[] = new CartLine($item->product_id, $item->quantity, Money::of($item->unit_price, $currency));
        }

        return new CheckoutPreview($previewLines, $this->discountEvaluator->evaluate($cartLines, $currency));
    }

    public function place(User $user, Cart $cart, Address $address): Order
    {
        if (! $address->hasCoordinates()) {
            throw new CheckoutException('Delivery address is missing coordinates.');
        }

        $items = $this->availableItems($cart);

        if ($items->isEmpty()) {
            throw new CheckoutException('Your cart has no available items to check out.');
        }

        $currency = $this->currency();

        return DB::transaction(function () use ($user, $cart, $address, $items, $currency) {
            // Pre-lock read: rank candidate stores per product while nothing is locked yet.
            $candidatesByProduct = [];
            foreach ($items as $item) {
                $candidatesByProduct[$item->product_id] = Store::query()
                    ->nearestTo((float) $address->lat, (float) $address->lng, $item->product_id)
                    ->get();
            }

            // Lock every touched store_product row in a fixed (store_id, product_id) order to
            // avoid deadlocking against another checkout locking the same rows in reverse order.
            $pairs = [];
            foreach ($candidatesByProduct as $productId => $candidates) {
                foreach ($candidates as $candidate) {
                    $pairs[] = [$candidate->id, $productId, $candidate->store_product_id];
                }
            }
            usort($pairs, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

            $lockedByStoreProductId = [];
            foreach ($pairs as [, , $storeProductId]) {
                $lockedByStoreProductId[$storeProductId] ??= StoreProduct::query()
                    ->whereKey($storeProductId)
                    ->lockForUpdate()
                    ->first();
            }

            // Authoritative discount recompute, server-side, inside this same transaction —
            // the client's displayed total from the cart "quote" is never trusted.
            $cartLines = $items
                ->map(fn (CartItem $item) => new CartLine($item->product_id, $item->quantity, Money::of($item->unit_price, $currency)))
                ->values()
                ->all();
            $discountResult = $this->discountEvaluator->evaluate($cartLines, $currency);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'delivery_address_id' => $address->id,
                'delivery_lat' => $address->lat,
                'delivery_lng' => $address->lng,
                'status' => OrderStatus::Pending,
                'subtotal' => $discountResult->subtotal->getAmount(),
                'discount_type' => $discountResult->discountType,
                'discount_amount' => $discountResult->discountAmount->getAmount(),
                'total' => $discountResult->total->getAmount(),
                'currency' => $currency,
                'placed_at' => now(),
            ]);

            $anyLineAllocated = false;

            foreach ($items->values() as $index => $item) {
                $fulfilled = $this->allocateLine($order, $item, $index, $discountResult, $candidatesByProduct[$item->product_id], $lockedByStoreProductId, $currency);

                if ($fulfilled > 0) {
                    $anyLineAllocated = true;
                }
            }

            if (! $anyLineAllocated) {
                throw new CheckoutException('None of the items in your cart could be fulfilled by any store right now.');
            }

            $order->update(['status' => OrderStatus::Confirmed]);

            $cart->items()->delete();

            return $order->fresh(['items.allocations.store']);
        });
    }

    protected function allocateLine(
        Order $order,
        CartItem $item,
        int $index,
        DiscountResult $discountResult,
        Collection $candidates,
        array &$lockedByStoreProductId,
        string $currency
    ): int {
        $lineDiscount = $discountResult->appliedLineDiscount($index);
        $lineSubtotal = Money::of($item->unit_price, $currency)->multipliedBy($item->quantity);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'product_name_snapshot' => $item->product->name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_subtotal' => $lineSubtotal->getAmount(),
            'product_discount_percent' => $discountResult->discountType === DiscountType::Product
                ? $this->discountEvaluator->productDiscountPercentFor($item->product_id, $item->quantity)
                : null,
            'line_discount_amount' => $lineDiscount->getAmount(),
            'line_total' => $lineSubtotal->minus($lineDiscount)->getAmount(),
            'fulfillment_status' => OrderItemFulfillmentStatus::Pending,
        ]);

        // Re-rank using the just-locked, authoritative quantities (not the pre-lock read).
        $lockedCandidates = $candidates->map(function (Store $candidate) use ($lockedByStoreProductId) {
            $candidate->available_quantity = $lockedByStoreProductId[$candidate->store_product_id]?->quantity_on_hand ?? 0;

            return $candidate;
        });

        [$allocations, $fulfilled] = $this->distribute($item->quantity, $lockedCandidates);

        foreach ($allocations as $allocation) {
            $storeProductId = $lockedCandidates->firstWhere('id', $allocation->storeId)->store_product_id;
            $locked = $lockedByStoreProductId[$storeProductId];
            $newQuantity = $locked->quantity_on_hand - $allocation->quantity;

            OrderItemAllocation::create([
                'order_item_id' => $orderItem->id,
                'store_id' => $allocation->storeId,
                'quantity_allocated' => $allocation->quantity,
                'unit_price' => $item->unit_price,
                'distance_km' => $allocation->distanceKm,
            ]);

            StoreProduct::whereKey($locked->id)
                ->where('quantity_on_hand', '>=', $allocation->quantity)
                ->update(['quantity_on_hand' => $newQuantity]);

            InventoryMovement::create([
                'store_product_id' => $locked->id,
                'type' => MovementType::Decrement,
                'quantity_delta' => -$allocation->quantity,
                'balance_after' => $newQuantity,
                'reason' => "Order {$order->order_number}",
            ]);

            $locked->quantity_on_hand = $newQuantity;
        }

        $orderItem->update([
            'fulfillment_status' => match (true) {
                $fulfilled === 0 => OrderItemFulfillmentStatus::Failed,
                $fulfilled < $item->quantity => OrderItemFulfillmentStatus::Partial,
                default => OrderItemFulfillmentStatus::Allocated,
            },
        ]);

        return $fulfilled;
    }

    /**
     * Nearest-first greedy distribution: take as much as the closest candidate has, then move
     * to the next, until the requested quantity is met or candidates run out. A single loop
     * implements both the "prefer one store" rule (satisfied in its first iteration whenever
     * the nearest store alone covers the quantity) and the multi-store split fallback.
     *
     * @param  Collection<int, Store>  $candidates  Distance-sorted, each carrying `available_quantity`.
     * @return array{0: array<int, PlannedAllocation>, 1: int} [allocations, totalFulfilled]
     */
    protected function distribute(int $required, Collection $candidates): array
    {
        $remaining = $required;
        $allocations = [];

        foreach ($candidates as $candidate) {
            if ($remaining <= 0) {
                break;
            }

            $available = (int) $candidate->available_quantity;

            if ($available <= 0) {
                continue;
            }

            $take = min($remaining, $available);

            $allocations[] = new PlannedAllocation(
                storeId: $candidate->id,
                storeName: $candidate->name,
                quantity: $take,
                distanceKm: $candidate->distance_km !== null ? round((float) $candidate->distance_km, 2) : null,
            );

            $remaining -= $take;
        }

        return [$allocations, $required - $remaining];
    }

    protected function availableItems(Cart $cart): Collection
    {
        return $cart->items()->with('product')->get()
            ->filter(fn (CartItem $item) => $item->product && $item->product->status === ProductStatus::Active)
            ->values();
    }

    protected function currency(): string
    {
        return config('shop.currency');
    }

    protected function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
