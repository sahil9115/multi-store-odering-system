<?php

namespace Tests\Feature;

use App\Domain\Ordering\CheckoutException;
use App\Domain\Ordering\OrderPlacer;
use App\Enums\OrderItemFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected OrderPlacer $orderPlacer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderPlacer = app(OrderPlacer::class);
    }

    /** Delivery address near (0, 0); stores are placed at increasing distance from it. */
    protected function deliveryAddress(User $user): Address
    {
        return Address::factory()->create(['user_id' => $user->id, 'lat' => 0.0, 'lng' => 0.0, 'is_default' => true]);
    }

    protected function storeAt(float $lat, float $lng, string $name = 'Store'): Store
    {
        return Store::factory()->create(['name' => $name, 'lat' => $lat, 'lng' => $lng]);
    }

    protected function addToCart(User $user, Product $product, int $quantity): void
    {
        $user->currentCart()->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
        ]);
    }

    public function test_a_single_nearby_store_that_covers_the_full_quantity_is_preferred(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10]);

        $near = $this->storeAt(0.01, 0.01, 'Near');
        $far = $this->storeAt(5, 5, 'Far');
        StoreProduct::factory()->create(['store_id' => $near->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);
        StoreProduct::factory()->create(['store_id' => $far->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $this->addToCart($user, $product, 5);

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $item = $order->items->first();
        $this->assertCount(1, $item->allocations);
        $this->assertSame($near->id, $item->allocations->first()->store_id);
        $this->assertSame(5, $item->allocations->first()->quantity_allocated);
        $this->assertSame(OrderItemFulfillmentStatus::Allocated, $item->fulfillment_status);
    }

    public function test_quantity_is_split_across_multiple_stores_nearest_first_when_one_store_is_insufficient(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10]);

        $near = $this->storeAt(0.01, 0.01, 'Near');
        $mid = $this->storeAt(1, 1, 'Mid');
        $far = $this->storeAt(5, 5, 'Far');
        StoreProduct::factory()->create(['store_id' => $near->id, 'product_id' => $product->id, 'quantity_on_hand' => 3]);
        StoreProduct::factory()->create(['store_id' => $mid->id, 'product_id' => $product->id, 'quantity_on_hand' => 4]);
        StoreProduct::factory()->create(['store_id' => $far->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

        $this->addToCart($user, $product, 10);

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $item = $order->items->first();
        $this->assertCount(3, $item->allocations);
        $this->assertSame([$near->id, $mid->id, $far->id], $item->allocations->pluck('store_id')->all());
        $this->assertSame([3, 4, 3], $item->allocations->pluck('quantity_allocated')->all());
        $this->assertSame(OrderItemFulfillmentStatus::Allocated, $item->fulfillment_status);
    }

    public function test_quantity_exactly_equal_to_nearest_stores_remaining_stock_is_fulfilled_by_that_store_alone(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10]);

        $near = $this->storeAt(0.01, 0.01, 'Near');
        StoreProduct::factory()->create(['store_id' => $near->id, 'product_id' => $product->id, 'quantity_on_hand' => 7]);

        $this->addToCart($user, $product, 7);

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $item = $order->items->first();
        $this->assertCount(1, $item->allocations);
        $this->assertSame(0, StoreProduct::where('store_id', $near->id)->value('quantity_on_hand'));
    }

    public function test_a_line_that_cannot_be_fully_covered_is_capped_not_rejected_and_the_order_still_places(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $productA = Product::factory()->create(['price' => 10]);
        $productB = Product::factory()->create(['price' => 5]);

        $store = $this->storeAt(0.01, 0.01);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $productA->id, 'quantity_on_hand' => 2]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $productB->id, 'quantity_on_hand' => 20]);

        $this->addToCart($user, $productA, 10); // only 2 available
        $this->addToCart($user, $productB, 3); // fully available

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $this->assertSame(OrderStatus::Confirmed, $order->status);

        $itemA = $order->items->firstWhere('product_id', $productA->id);
        $itemB = $order->items->firstWhere('product_id', $productB->id);

        $this->assertSame(OrderItemFulfillmentStatus::Partial, $itemA->fulfillment_status);
        $this->assertSame(2, $itemA->allocatedQuantity());
        $this->assertSame(OrderItemFulfillmentStatus::Allocated, $itemB->fulfillment_status);
        $this->assertSame(3, $itemB->allocatedQuantity());
    }

    public function test_a_line_with_zero_stock_anywhere_is_marked_failed_but_the_order_still_places_for_other_lines(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $outOfStock = Product::factory()->create(['price' => 10]);
        $inStock = Product::factory()->create(['price' => 5]);

        $store = $this->storeAt(0.01, 0.01);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $outOfStock->id, 'quantity_on_hand' => 0]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $inStock->id, 'quantity_on_hand' => 5]);

        $this->addToCart($user, $outOfStock, 2);
        $this->addToCart($user, $inStock, 2);

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $failedItem = $order->items->firstWhere('product_id', $outOfStock->id);
        $this->assertSame(OrderItemFulfillmentStatus::Failed, $failedItem->fulfillment_status);
        $this->assertCount(0, $failedItem->allocations);
    }

    public function test_placing_an_order_with_every_line_unfulfillable_throws_and_creates_no_order(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10]);

        $store = $this->storeAt(0.01, 0.01);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 0]);

        $this->addToCart($user, $product, 2);

        $this->expectException(CheckoutException::class);

        try {
            $this->orderPlacer->place($user, $user->currentCart(), $address);
        } finally {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_checkout_is_blocked_when_the_delivery_address_has_no_coordinates(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => null, 'lng' => null]);
        $product = Product::factory()->create(['price' => 10]);
        $store = $this->storeAt(0.01, 0.01);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);

        $this->addToCart($user, $product, 2);

        $this->expectException(CheckoutException::class);

        $this->orderPlacer->place($user, $user->currentCart(), $address);
    }

    public function test_checkout_is_blocked_when_the_cart_is_empty(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);

        $this->expectException(CheckoutException::class);

        $this->orderPlacer->place($user, $user->currentCart(), $address);
    }

    public function test_a_deactivated_product_still_in_the_cart_is_excluded_from_checkout(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10, 'status' => ProductStatus::Draft]);
        $store = $this->storeAt(0.01, 0.01);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);

        $this->addToCart($user, $product, 2);

        $this->expectException(CheckoutException::class);

        $this->orderPlacer->place($user, $user->currentCart(), $address);
    }

    public function test_equidistant_stores_break_the_tie_by_lowest_store_id(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10]);

        $storeA = $this->storeAt(1.0, 1.0, 'A');
        $storeB = $this->storeAt(-1.0, -1.0, 'B'); // same distance from (0,0) as A
        StoreProduct::factory()->create(['store_id' => $storeA->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);
        StoreProduct::factory()->create(['store_id' => $storeB->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);

        $this->addToCart($user, $product, 3);

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $expectedFirst = min($storeA->id, $storeB->id);
        $this->assertSame($expectedFirst, $order->items->first()->allocations->first()->store_id);
    }

    public function test_successful_checkout_empties_the_cart_and_applies_the_authoritative_discount(): void
    {
        $user = User::factory()->create();
        $address = $this->deliveryAddress($user);
        $product = Product::factory()->create(['price' => 10]);
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 5, 'discount_percent' => 10]);

        $store = $this->storeAt(0.01, 0.01);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $this->addToCart($user, $product, 5);

        $order = $this->orderPlacer->place($user, $user->currentCart(), $address);

        $this->assertSame('5.00', (string) $order->discount_amount);
        $this->assertSame('45.00', (string) $order->total);
        $this->assertSame(0, $user->currentCart()->items()->count());
    }

    /**
     * Proves the guarded decrement (`WHERE quantity_on_hand >= ?`) prevents overselling even
     * if two updates race without going through OrderPlacer's row lock — the second racing
     * write must no-op rather than drive stock negative.
     */
    public function test_the_guarded_stock_decrement_never_oversells_under_a_simulated_race(): void
    {
        $store = $this->storeAt(0.01, 0.01);
        $product = Product::factory()->create();
        $storeProduct = StoreProduct::factory()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 5,
        ]);

        // Two "concurrent" attempts to take 5 units each from a store with only 5 in stock.
        $firstTaken = DB::table('store_product')
            ->where('id', $storeProduct->id)
            ->where('quantity_on_hand', '>=', 5)
            ->update(['quantity_on_hand' => DB::raw('quantity_on_hand - 5')]);

        $secondTaken = DB::table('store_product')
            ->where('id', $storeProduct->id)
            ->where('quantity_on_hand', '>=', 5)
            ->update(['quantity_on_hand' => DB::raw('quantity_on_hand - 5')]);

        $this->assertSame(1, $firstTaken);
        $this->assertSame(0, $secondTaken);
        $this->assertSame(0, StoreProduct::find($storeProduct->id)->quantity_on_hand);
    }
}
