<?php

namespace Tests\Feature;

use App\Domain\Ordering\OrderPlacer;
use App\Domain\Ordering\ReturnException;
use App\Domain\Ordering\ReturnProcessor;
use App\Enums\DiscountType;
use App\Enums\OrderItemFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\PlatformDiscount;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected OrderPlacer $orderPlacer;

    protected ReturnProcessor $returnProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderPlacer = app(OrderPlacer::class);
        $this->returnProcessor = app(ReturnProcessor::class);
    }

    protected function placeOrder(User $user, Product $product, int $quantity, Store $store, float $price = 10): Order
    {
        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $price]);

        return $this->orderPlacer->place($user, $user->currentCart(), $address);
    }

    public function test_returning_a_quantity_restocks_the_originating_store(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        $storeProduct = StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $this->assertSame(15, $storeProduct->fresh()->quantity_on_hand);

        $item = $order->items->first();
        $this->returnProcessor->returnItem($item, 2, $user);

        $this->assertSame(17, $storeProduct->fresh()->quantity_on_hand);
        $this->assertSame(2, $item->fresh()->returned_quantity);
    }

    public function test_returning_a_split_line_restocks_each_originating_store_proportionally(): void
    {
        $user = User::factory()->create();
        $near = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01, 'name' => 'Near']);
        $far = Store::factory()->create(['lat' => 5, 'lng' => 5, 'name' => 'Far']);
        $product = Product::factory()->create(['price' => 10]);
        $nearStock = StoreProduct::factory()->create(['store_id' => $near->id, 'product_id' => $product->id, 'quantity_on_hand' => 3]);
        $farStock = StoreProduct::factory()->create(['store_id' => $far->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

        $order = $this->placeOrder($user, $product, 8, $near); // 3 from near, 5 from far
        $this->assertSame(0, $nearStock->fresh()->quantity_on_hand);
        $this->assertSame(5, $farStock->fresh()->quantity_on_hand);

        $item = $order->items->first();
        // Return 5: should take 3 back from the first allocation (near) then 2 from the second (far).
        $this->returnProcessor->returnItem($item, 5, $user);

        $this->assertSame(3, $nearStock->fresh()->quantity_on_hand);
        $this->assertSame(7, $farStock->fresh()->quantity_on_hand);
    }

    public function test_cannot_return_more_than_was_actually_delivered(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $productA = Product::factory()->create(['price' => 10]);
        $productB = Product::factory()->create(['price' => 5]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $productA->id, 'quantity_on_hand' => 2]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $productB->id, 'quantity_on_hand' => 20]);

        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $user->currentCart()->items()->create(['product_id' => $productA->id, 'quantity' => 10, 'unit_price' => 10]); // only 2 fulfilled
        $user->currentCart()->items()->create(['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 5]);
        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $partialItem = $order->items->firstWhere('product_id', $productA->id);
        $this->assertSame(OrderItemFulfillmentStatus::Partial, $partialItem->fulfillment_status);

        $this->expectException(ReturnException::class);
        // Requested 10, only 2 delivered — trying to return 3 must fail even though 3 < requested.
        $this->returnProcessor->returnItem($partialItem, 3, $user);
    }

    public function test_returning_the_only_line_drops_it_below_its_product_discount_threshold(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 5, 'discount_percent' => 10]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $this->assertSame(DiscountType::Product, $order->discount_type);
        $this->assertSame('5.00', (string) $order->discount_amount);

        $item = $order->items->first();
        $this->returnProcessor->returnItem($item, 2, $user); // 3 left, below the min_quantity=5 tier

        $order->refresh();
        $this->assertSame(DiscountType::None, $order->discount_type);
        $this->assertSame('0.00', (string) $order->discount_amount);
        $this->assertSame('30.00', (string) $order->total); // 3 remaining * $10, no discount
    }

    public function test_returning_enough_drops_the_order_below_the_platform_discount_minimum(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 10]);

        $order = $this->placeOrder($user, $product, 10, $store); // subtotal 100, qualifies
        $this->assertSame(DiscountType::Platform, $order->discount_type);
        $this->assertSame('10.00', (string) $order->discount_amount);

        $item = $order->items->first();
        $this->returnProcessor->returnItem($item, 6, $user); // 4 left = $40, below $50 minimum

        $order->refresh();
        $this->assertSame(DiscountType::None, $order->discount_type);
        $this->assertSame('40.00', (string) $order->total);
    }

    public function test_a_partial_return_that_still_meets_the_threshold_keeps_the_discount(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 5, 'discount_percent' => 10]);

        $order = $this->placeOrder($user, $product, 10, $store);

        $item = $order->items->first();
        $this->returnProcessor->returnItem($item, 3, $user); // 7 left, still >= 5

        $order->refresh();
        $this->assertSame(DiscountType::Product, $order->discount_type);
        $this->assertSame('7.00', (string) $order->discount_amount); // 10% of 7*10
    }

    public function test_product_and_platform_discounts_stay_mutually_exclusive_after_a_return(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 3, 'discount_percent' => 5]);
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 30]);

        $order = $this->placeOrder($user, $product, 10, $store); // platform wins (30 > 5)
        $this->assertSame(DiscountType::Platform, $order->discount_type);

        $item = $order->items->first();
        $this->returnProcessor->returnItem($item, 6, $user); // 4 left = $40, below platform's $50, but still >= product's min_quantity=3

        $order->refresh();
        $this->assertSame(DiscountType::Product, $order->discount_type);
        $this->assertSame('2.00', (string) $order->discount_amount); // 5% of 4*10
        // Never both at once:
        $this->assertNotSame(DiscountType::Platform, $order->discount_type);
    }

    public function test_returning_the_entire_order_cancels_it(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 4, $store);
        $item = $order->items->first();

        $this->returnProcessor->returnItem($item, 4, $user);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('0.00', (string) $order->total);
        $this->assertSame(OrderItemFulfillmentStatus::Returned, $item->fresh()->fulfillment_status);
    }

    public function test_a_second_partial_return_on_the_same_line_accumulates_correctly(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        $storeProduct = StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 10, $store);
        $item = $order->items->first();

        $this->returnProcessor->returnItem($item, 2, $user);
        $this->returnProcessor->returnItem($item, 3, $user);

        $this->assertSame(5, $item->fresh()->returned_quantity);
        $this->assertSame(15, $storeProduct->fresh()->quantity_on_hand); // 20 - 10 + 2 + 3
        $this->assertSame(5, $item->fresh()->remainingQuantity());
    }

    /**
     * Regression: `orders.placed_at` was the first TIMESTAMP column in the table, which on a
     * MySQL/MariaDB server with `explicit_defaults_for_timestamp = OFF` (a common default,
     * including stock XAMPP/MariaDB) silently gets `ON UPDATE CURRENT_TIMESTAMP` — so any
     * update to the order (like a return's recalculation) was overwriting the order's original
     * placement time. Fixed by switching the column to DATETIME.
     */
    public function test_a_return_does_not_change_the_orders_original_placed_at_timestamp(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $originalPlacedAt = $order->placed_at->copy();

        $this->travel(10)->minutes();
        $this->returnProcessor->returnItem($order->items->first(), 2, $user);

        $this->assertTrue($originalPlacedAt->eq($order->fresh()->placed_at));
    }

    public function test_returning_zero_or_negative_quantity_is_rejected(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $item = $order->items->first();

        $this->expectException(ReturnException::class);
        $this->returnProcessor->returnItem($item, 0, $user);
    }

    public function test_cannot_return_from_an_already_cancelled_order(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $order->update(['status' => OrderStatus::Cancelled]);

        $this->expectException(ReturnException::class);
        $this->returnProcessor->returnItem($order->items->first(), 1, $user);
    }

    public function test_a_line_with_zero_delivered_quantity_cannot_be_returned_at_all(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 0]);

        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $productB = Product::factory()->create(['price' => 5]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $productB->id, 'quantity_on_hand' => 10]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10]);
        $user->currentCart()->items()->create(['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 5]);
        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $failedItem = $order->items->firstWhere('product_id', $product->id);
        $this->assertSame(OrderItemFulfillmentStatus::Failed, $failedItem->fulfillment_status);
        $this->assertSame(0, $failedItem->remainingQuantity());

        $this->expectException(ReturnException::class);
        $this->returnProcessor->returnItem($failedItem, 1, $user);
    }

    public function test_a_return_writes_an_audit_trail_row(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $item = $order->items->first();

        $this->returnProcessor->returnItem($item, 2, $user, 'Changed my mind');

        $this->assertDatabaseHas('order_returns', [
            'order_item_id' => $item->id,
            'quantity' => 2,
            'reason' => 'Changed my mind',
            'created_by' => $user->id,
        ]);
    }

    public function test_a_return_writes_an_inventory_movement_for_the_restock(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $order = $this->placeOrder($user, $product, 5, $store);
        $item = $order->items->first();

        $this->returnProcessor->returnItem($item, 2, $user);

        $this->assertDatabaseHas('inventory_movements', [
            'type' => 'increment',
            'quantity_delta' => 2,
        ]);
    }
}
