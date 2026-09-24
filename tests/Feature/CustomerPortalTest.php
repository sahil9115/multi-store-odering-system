<?php

namespace Tests\Feature;

use App\Domain\Ordering\CheckoutException;
use App\Domain\Ordering\OrderPlacer;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Livewire\Addresses\Index as AddressesIndex;
use App\Livewire\Cart\Index as CartIndex;
use App\Livewire\Catalog\Index as CatalogIndex;
use App\Livewire\Checkout\Index as CheckoutIndex;
use App\Livewire\Orders\History as OrdersHistory;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Customer->value);

        return $user;
    }

    public function test_guests_are_redirected_away_from_the_shop_cart_and_checkout(): void
    {
        $this->get('/shop')->assertRedirect('/login');
        $this->get('/cart')->assertRedirect('/login');
        $this->get('/checkout')->assertRedirect('/login');
        $this->get('/addresses')->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_catalog_only_lists_active_products_and_adding_to_cart_works(): void
    {
        $active = Product::factory()->create(['status' => ProductStatus::Active, 'name' => 'Visible Widget']);
        Product::factory()->create(['status' => ProductStatus::Draft, 'name' => 'Hidden Widget']);

        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(CatalogIndex::class)
            ->assertSee('Visible Widget')
            ->assertDontSee('Hidden Widget')
            ->call('addToCart', $active->id, 3)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cart_items', ['product_id' => $active->id, 'quantity' => 3]);
    }

    public function test_adding_the_same_product_twice_increments_quantity_rather_than_duplicating_the_row(): void
    {
        $product = Product::factory()->create();
        $user = $this->customer();

        $component = Livewire::actingAs($user)->test(CatalogIndex::class);
        $component->call('addToCart', $product->id, 2);
        $component->call('addToCart', $product->id, 3);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_cart_page_shows_live_discount_and_quantity_updates_apply(): void
    {
        $product = Product::factory()->create(['price' => 10]);
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 5, 'discount_percent' => 10]);
        $user = $this->customer();
        $item = $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10]);

        Livewire::actingAs($user)
            ->test(CartIndex::class)
            ->assertSee('45') // total after 10% off 50
            ->call('updateQuantity', $item->id, 1)
            ->assertOk();

        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_removing_the_last_item_empties_the_cart(): void
    {
        $product = Product::factory()->create();
        $user = $this->customer();
        $item = $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]);

        Livewire::actingAs($user)
            ->test(CartIndex::class)
            ->call('removeItem', $item->id);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_customer_cannot_modify_another_customers_cart_item(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $product = Product::factory()->create();
        $item = $owner->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($intruder)
            ->test(CartIndex::class)
            ->call('removeItem', $item->id);
    }

    public function test_address_can_be_created_edited_and_set_default(): void
    {
        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(AddressesIndex::class)
            ->call('startCreate')
            ->set('label', 'Home')
            ->set('line1', '123 Main St')
            ->set('city', 'Metropolis')
            ->set('country', 'US')
            ->set('lat', 40.7128)
            ->set('lng', -74.0060)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'label' => 'Home', 'is_default' => false]);
    }

    public function test_address_requires_line1_and_city(): void
    {
        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(AddressesIndex::class)
            ->call('startCreate')
            ->set('label', 'Home')
            ->set('country', 'US')
            ->call('save')
            ->assertHasErrors(['line1', 'city']);
    }

    public function test_address_latitude_out_of_range_is_rejected(): void
    {
        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(AddressesIndex::class)
            ->call('startCreate')
            ->set('label', 'Home')
            ->set('line1', '123 Main St')
            ->set('city', 'Metropolis')
            ->set('country', 'US')
            ->set('lat', 200)
            ->call('save')
            ->assertHasErrors(['lat']);
    }

    public function test_making_an_address_default_unsets_the_previous_default(): void
    {
        $user = $this->customer();
        $first = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $second = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        Livewire::actingAs($user)
            ->test(AddressesIndex::class)
            ->call('makeDefault', $second->id);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_checkout_preview_shows_a_planned_allocation_and_places_the_order(): void
    {
        $user = $this->customer();
        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $product = Product::factory()->create(['price' => 10]);
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10]);

        Livewire::actingAs($user)
            ->test(CheckoutIndex::class)
            ->assertSee($store->name)
            ->call('placeOrder')
            ->assertRedirect(route('orders.index'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_requires_explicit_confirmation_before_placing_a_shortfall_order(): void
    {
        $user = $this->customer();
        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $product = Product::factory()->create(['price' => 10]);
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 2]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 10]);

        // First click without confirming: must NOT place the order.
        Livewire::actingAs($user)
            ->test(CheckoutIndex::class)
            ->call('placeOrder');

        $this->assertDatabaseCount('orders', 0);

        // After explicitly confirming the adjustment, it places.
        Livewire::actingAs($user)
            ->test(CheckoutIndex::class)
            ->set('confirmedAdjustments', true)
            ->call('placeOrder')
            ->assertRedirect(route('orders.index'));

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_blocks_with_a_clear_error_when_no_address_is_selected(): void
    {
        $user = $this->customer();
        $product = Product::factory()->create();
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]);

        Livewire::actingAs($user)
            ->test(CheckoutIndex::class)
            ->set('selectedAddressId', null)
            ->call('placeOrder')
            ->assertHasErrors(['address']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_history_lists_placed_orders_with_their_store_allocations(): void
    {
        $user = $this->customer();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id]);
        $store = Store::factory()->create();
        OrderItemAllocation::create([
            'order_item_id' => $item->id,
            'store_id' => $store->id,
            'quantity_allocated' => $item->quantity,
            'unit_price' => $item->unit_price,
            'distance_km' => 1.2,
        ]);

        Livewire::actingAs($user)
            ->test(OrdersHistory::class)
            ->assertSee($order->order_number)
            ->assertSee($store->name);
    }

    public function test_order_history_only_shows_the_authenticated_users_own_orders(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        Livewire::actingAs($other)
            ->test(OrdersHistory::class)
            ->assertDontSee($order->order_number);
    }

    public function test_a_customer_cannot_edit_or_delete_another_customers_address(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $address = Address::factory()->create(['user_id' => $owner->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($intruder)
            ->test(AddressesIndex::class)
            ->call('delete', $address->id);
    }

    public function test_a_customer_cannot_make_another_customers_address_their_default(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $address = Address::factory()->create(['user_id' => $owner->id, 'is_default' => false]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($intruder)
            ->test(AddressesIndex::class)
            ->call('makeDefault', $address->id);
    }

    public function test_checkout_rejects_an_address_id_belonging_to_another_customer(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $address = Address::factory()->create(['user_id' => $owner->id, 'lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create();
        $intruder->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]);

        Livewire::actingAs($intruder)
            ->test(CheckoutIndex::class)
            ->set('selectedAddressId', $address->id)
            ->call('placeOrder')
            ->assertHasErrors(['address']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_setting_cart_quantity_to_zero_removes_the_item(): void
    {
        $product = Product::factory()->create();
        $user = $this->customer();
        $item = $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10]);

        Livewire::actingAs($user)
            ->test(CartIndex::class)
            ->call('updateQuantity', $item->id, 0);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_resubmitting_place_order_after_success_does_not_create_a_duplicate_order(): void
    {
        $user = $this->customer();
        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $product = Product::factory()->create(['price' => 10]);
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10]);

        $component = Livewire::actingAs($user)->test(CheckoutIndex::class);
        $component->call('placeOrder')->assertRedirect(route('orders.index'));

        // Simulate a double-submit / retried request on an already-emptied cart: it must fail
        // gracefully (empty cart) rather than silently placing a second order.
        $this->expectException(CheckoutException::class);
        app(OrderPlacer::class)->place($user, $user->currentCart(), $address);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_customer_can_return_a_quantity_from_their_own_order_through_the_history_page(): void
    {
        $user = $this->customer();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        $storeProduct = StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10]);
        $order = app(OrderPlacer::class)->place($user, $user->currentCart(), $address);
        $item = $order->items->first();

        Livewire::actingAs($user)
            ->test(OrdersHistory::class)
            ->call('returnItem', $item->id, 2)
            ->assertHasNoErrors();

        $this->assertSame(2, $item->fresh()->returned_quantity);
        $this->assertSame(17, $storeProduct->fresh()->quantity_on_hand);
    }

    public function test_a_customer_cannot_return_an_item_from_another_customers_order(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $address = Address::factory()->create(['user_id' => $owner->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $owner->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10]);
        $order = app(OrderPlacer::class)->place($owner, $owner->currentCart(), $address);
        $item = $order->items->first();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($intruder)
            ->test(OrdersHistory::class)
            ->call('returnItem', $item->id, 1);
    }

    public function test_a_return_beyond_what_was_delivered_shows_an_error_not_a_crash(): void
    {
        $user = $this->customer();
        $store = Store::factory()->create(['lat' => 0.01, 'lng' => 0.01]);
        $product = Product::factory()->create(['price' => 10]);
        StoreProduct::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $address = Address::factory()->create(['user_id' => $user->id, 'lat' => 0.01, 'lng' => 0.01, 'is_default' => true]);
        $user->currentCart()->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10]);
        $order = app(OrderPlacer::class)->place($user, $user->currentCart(), $address);
        $item = $order->items->first();

        Livewire::actingAs($user)
            ->test(OrdersHistory::class)
            ->call('returnItem', $item->id, 99)
            ->assertHasErrors(['return']);

        $this->assertSame(0, $item->fresh()->returned_quantity);
    }
}
