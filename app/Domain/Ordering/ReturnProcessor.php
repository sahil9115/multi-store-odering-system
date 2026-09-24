<?php

namespace App\Domain\Ordering;

use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\OrderItemFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\OrderReturn;
use App\Models\StoreProduct;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Processes a customer-initiated return of some (or all) of an order line's delivered
 * quantity: restocks the exact store(s) that fulfilled it, then recalculates the whole
 * order's discount and totals from the remaining (delivered − returned) quantities — a
 * discount that no longer meets its threshold on what's left is dropped, matching
 * DiscountEvaluator's mutual-exclusivity rule.
 *
 * Only what was actually delivered (OrderItem::allocatedQuantity(), not the original
 * requested quantity) can be returned — a partially-fulfilled line can't return more than
 * it received.
 */
class ReturnProcessor
{
    /** @var array<int, OrderStatus> */
    protected const RETURNABLE_STATUSES = [
        OrderStatus::Confirmed,
        OrderStatus::Processing,
        OrderStatus::Fulfilled,
    ];

    public function __construct(protected DiscountEvaluator $discountEvaluator) {}

    public function returnItem(OrderItem $orderItem, int $quantity, ?User $actor = null, ?string $reason = null): Order
    {
        if ($quantity <= 0) {
            throw new ReturnException('Return quantity must be at least 1.');
        }

        $order = $orderItem->order;

        if (! in_array($order->status, self::RETURNABLE_STATUSES, true)) {
            throw new ReturnException('This order is no longer eligible for returns.');
        }

        $maxReturnable = $orderItem->remainingQuantity();

        if ($quantity > $maxReturnable) {
            throw new ReturnException("Only {$maxReturnable} unit(s) of this item can be returned.");
        }

        return DB::transaction(function () use ($order, $orderItem, $quantity, $actor, $reason) {
            $this->restock($orderItem, $quantity, $order);

            OrderReturn::create([
                'order_item_id' => $orderItem->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'created_by' => $actor?->id,
            ]);

            $orderItem->increment('returned_quantity', $quantity);
            $orderItem->refresh();

            if ($orderItem->remainingQuantity() <= 0) {
                $orderItem->update(['fulfillment_status' => OrderItemFulfillmentStatus::Returned]);
            }

            $this->recalculateOrder($order);

            return $order->fresh(['items.allocations.store', 'items.returns']);
        });
    }

    /**
     * Restocks the exact stores that fulfilled this line, in the same order the allocations
     * were created, capping at what each allocation has left to give back.
     */
    protected function restock(OrderItem $orderItem, int $quantity, Order $order): void
    {
        $allocations = OrderItemAllocation::query()
            ->where('order_item_id', $orderItem->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;

        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $available = $allocation->remainingQuantity();

            if ($available <= 0) {
                continue;
            }

            $take = min($remaining, $available);

            $storeProduct = StoreProduct::query()
                ->where('store_id', $allocation->store_id)
                ->where('product_id', $orderItem->product_id)
                ->lockForUpdate()
                ->first();

            if (! $storeProduct) {
                throw new ReturnException('Unable to restock: the originating store inventory record no longer exists.');
            }

            $newQuantity = $storeProduct->quantity_on_hand + $take;

            $storeProduct->update(['quantity_on_hand' => $newQuantity]);

            InventoryMovement::create([
                'store_product_id' => $storeProduct->id,
                'order_item_allocation_id' => $allocation->id,
                'type' => MovementType::Increment,
                'quantity_delta' => $take,
                'balance_after' => $newQuantity,
                'reason' => "Return for order {$order->order_number}",
                'created_by' => null,
            ]);

            $allocation->update(['returned_quantity' => $allocation->returned_quantity + $take]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new ReturnException('Unable to process the full return — allocation records are inconsistent.');
        }
    }

    /**
     * Recomputes the order's discount and totals from each line's remaining (delivered −
     * returned) quantity, using the line's original snapshot unit price. A discount that no
     * longer meets its threshold on what's left is dropped; product and platform discounts
     * stay mutually exclusive, same as at placement.
     */
    protected function recalculateOrder(Order $order): void
    {
        $items = $order->items()->get();
        $currency = $order->currency;

        $linesByItemId = [];

        foreach ($items as $item) {
            $remaining = $item->remainingQuantity();

            if ($remaining <= 0) {
                continue;
            }

            $linesByItemId[$item->id] = new CartLine($item->product_id, $remaining, Money::of($item->unit_price, $currency));
        }

        $lines = array_values($linesByItemId);
        $itemIds = array_keys($linesByItemId);

        $result = $this->discountEvaluator->evaluate($lines, $currency);

        foreach ($items as $item) {
            $index = array_search($item->id, $itemIds, true);

            if ($index === false) {
                // Fully returned: nothing left on this line.
                $item->update([
                    'line_subtotal' => 0,
                    'product_discount_percent' => null,
                    'line_discount_amount' => 0,
                    'line_total' => 0,
                ]);

                continue;
            }

            $line = $lines[$index];
            $lineDiscount = $result->appliedLineDiscount($index);

            $item->update([
                'line_subtotal' => $line->subtotal()->getAmount(),
                'product_discount_percent' => $result->discountType === DiscountType::Product
                    ? $this->discountEvaluator->productDiscountPercentFor($item->product_id, $line->quantity)
                    : null,
                'line_discount_amount' => $lineDiscount->getAmount(),
                'line_total' => $line->subtotal()->minus($lineDiscount)->getAmount(),
            ]);
        }

        $order->update([
            'subtotal' => $result->subtotal->getAmount(),
            'discount_type' => $result->discountType,
            'discount_amount' => $result->discountAmount->getAmount(),
            'total' => $result->total->getAmount(),
            'status' => $lines === [] ? OrderStatus::Cancelled : $order->status,
        ]);
    }
}
