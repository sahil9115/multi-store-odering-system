<?php

namespace Database\Seeders;

use App\Domain\Ordering\CheckoutException;
use App\Domain\Ordering\OrderPlacer;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\PlatformDiscount;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic demo data for manual testing / click-through verification: five stores spread
 * across a city, a product catalog, per-store inventory (deliberately uneven — some products
 * are low or out of stock at the nearest store so the allocation split/shortfall paths are
 * reachable by hand), a couple of discount rules, and a demo customer with a placed order.
 *
 * Local/dev only — never run against production data.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $stores = $this->seedStores();
        $products = $this->seedProducts();
        $this->seedInventory($stores, $products);
        $this->seedDiscounts($products);
        $customer = $this->seedDemoCustomer();
        $this->seedDemoOrder($customer, $products);
    }

    /**
     * @return array<string, Store>
     */
    protected function seedStores(): array
    {
        $definitions = [
            'downtown' => ['name' => 'Downtown Manhattan', 'code' => 'NYC-DT', 'address_line' => '123 Broadway, New York, NY', 'lat' => 40.7128, 'lng' => -74.0060],
            'brooklyn' => ['name' => 'Brooklyn Heights', 'code' => 'NYC-BK', 'address_line' => '45 Court St, Brooklyn, NY', 'lat' => 40.6782, 'lng' => -73.9442],
            'queens' => ['name' => 'Long Island City', 'code' => 'NYC-QN', 'address_line' => '10 Jackson Ave, Queens, NY', 'lat' => 40.7282, 'lng' => -73.7949],
            'bronx' => ['name' => 'Fordham Bronx', 'code' => 'NYC-BX', 'address_line' => '200 Fordham Rd, Bronx, NY', 'lat' => 40.8448, 'lng' => -73.8648],
            'staten_island' => ['name' => 'Staten Island', 'code' => 'NYC-SI', 'address_line' => '1 Bay St, Staten Island, NY', 'lat' => 40.5795, 'lng' => -74.1502],
        ];

        $stores = [];
        foreach ($definitions as $key => $attributes) {
            $stores[$key] = Store::query()->updateOrCreate(['code' => $attributes['code']], $attributes + ['status' => 'active']);
        }

        return $stores;
    }

    /**
     * @return array<string, Product>
     */
    protected function seedProducts(): array
    {
        $definitions = [
            'mouse' => ['name' => 'Wireless Mouse', 'sku' => 'ELEC-MOUSE-01', 'price' => 24.99],
            'keyboard' => ['name' => 'Mechanical Keyboard', 'sku' => 'ELEC-KEYB-01', 'price' => 79.99],
            'usb_cable' => ['name' => 'USB-C Cable 2m', 'sku' => 'ELEC-CABLE-01', 'price' => 9.99],
            'monitor' => ['name' => '27-inch Monitor', 'sku' => 'ELEC-MON-01', 'price' => 249.99],
            'laptop_stand' => ['name' => 'Aluminum Laptop Stand', 'sku' => 'ELEC-STAND-01', 'price' => 34.99],
            'webcam' => ['name' => 'HD Webcam', 'sku' => 'ELEC-CAM-01', 'price' => 49.99],
            'speaker' => ['name' => 'Bluetooth Speaker', 'sku' => 'ELEC-SPKR-01', 'price' => 59.99],
            'headphones' => ['name' => 'Noise Cancelling Headphones', 'sku' => 'ELEC-HP-01', 'price' => 199.99],
            'ssd' => ['name' => 'Portable SSD 1TB', 'sku' => 'ELEC-SSD-01', 'price' => 109.99],
            'chair' => ['name' => 'Ergonomic Office Chair', 'sku' => 'FURN-CHAIR-01', 'price' => 299.99],
            'desk_lamp' => ['name' => 'LED Desk Lamp', 'sku' => 'FURN-LAMP-01', 'price' => 29.99],
            'phone_case' => ['name' => 'Phone Case', 'sku' => 'ACC-CASE-01', 'price' => 14.99],
            'power_bank' => ['name' => 'Power Bank 20000mAh', 'sku' => 'ACC-BANK-01', 'price' => 39.99],
            'charger' => ['name' => 'Wireless Charger Pad', 'sku' => 'ACC-CHRG-01', 'price' => 19.99],
            'smart_watch' => ['name' => 'Smart Watch', 'sku' => 'ELEC-WATCH-01', 'price' => 149.99],
        ];

        $products = [];
        foreach ($definitions as $key => $attributes) {
            $products[$key] = Product::query()->updateOrCreate(
                ['sku' => $attributes['sku']],
                $attributes + [
                    'slug' => Str::slug($attributes['name']),
                    'description' => "High quality {$attributes['name']} for everyday use.",
                    'status' => ProductStatus::Active,
                ]
            );
        }

        return $products;
    }

    /**
     * @param  array<string, Store>  $stores
     * @param  array<string, Product>  $products
     */
    protected function seedInventory(array $stores, array $products): void
    {
        // Every store stocks a healthy default amount of everything...
        foreach ($stores as $store) {
            foreach ($products as $product) {
                StoreProduct::query()->updateOrCreate(
                    ['store_id' => $store->id, 'product_id' => $product->id],
                    ['quantity_on_hand' => rand(15, 60), 'reorder_level' => 10]
                );
            }
        }

        // ...except deliberate exceptions so the allocation engine's split/shortfall paths are
        // reachable by hand from the demo customer's address (near Downtown Manhattan):
        // low stock at the nearest store forces a multi-store split.
        StoreProduct::query()->updateOrCreate(
            ['store_id' => $stores['downtown']->id, 'product_id' => $products['mouse']->id],
            ['quantity_on_hand' => 2, 'reorder_level' => 10]
        );

        // out of stock at the nearest store, available further away.
        StoreProduct::query()->updateOrCreate(
            ['store_id' => $stores['downtown']->id, 'product_id' => $products['headphones']->id],
            ['quantity_on_hand' => 0, 'reorder_level' => 10]
        );

        // out of stock everywhere, to demo the "failed" fulfillment state.
        foreach ($stores as $store) {
            StoreProduct::query()->updateOrCreate(
                ['store_id' => $store->id, 'product_id' => $products['chair']->id],
                ['quantity_on_hand' => 0, 'reorder_level' => 5]
            );
        }
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function seedDiscounts(array $products): void
    {
        ProductDiscount::query()->updateOrCreate(
            ['product_id' => $products['mouse']->id, 'min_quantity' => 5],
            ['discount_percent' => 10, 'is_active' => true]
        );

        ProductDiscount::query()->updateOrCreate(
            ['product_id' => $products['usb_cable']->id, 'min_quantity' => 10],
            ['discount_percent' => 20, 'is_active' => true]
        );

        PlatformDiscount::query()->updateOrCreate(
            ['name' => 'Big Order Discount'],
            ['min_order_amount' => 200, 'discount_percent' => 15, 'is_active' => true, 'priority' => 0]
        );

        PlatformDiscount::query()->updateOrCreate(
            ['name' => 'Weekend Flash Sale'],
            ['min_order_amount' => 50, 'discount_percent' => 5, 'is_active' => true, 'priority' => 1]
        );
    }

    protected function seedDemoCustomer(): User
    {
        $customer = User::query()->firstOrCreate(
            ['email' => 'demo.customer@multistore.test'],
            ['name' => 'Dana Customer', 'password' => bcrypt('password')]
        );
        $customer->syncRoles([UserRole::Customer->value]);

        Address::query()->updateOrCreate(
            ['user_id' => $customer->id, 'label' => 'Home'],
            [
                'line1' => '350 5th Ave',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10118',
                'country' => 'US',
                'lat' => 40.7300,
                'lng' => -73.9950,
                'is_default' => true,
            ]
        );

        return $customer;
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function seedDemoOrder(User $customer, array $products): void
    {
        if ($customer->orders()->exists()) {
            return;
        }

        $cart = $customer->currentCart();
        $cart->items()->create(['product_id' => $products['mouse']->id, 'quantity' => 6, 'unit_price' => $products['mouse']->price]);
        $cart->items()->create(['product_id' => $products['keyboard']->id, 'quantity' => 1, 'unit_price' => $products['keyboard']->price]);

        $address = $customer->addresses()->where('is_default', true)->first();

        try {
            app(OrderPlacer::class)->place($customer, $cart, $address);
        } catch (CheckoutException) {
            // Inventory for this run made checkout impossible (e.g. reseeded with different
            // random quantities) — skip the demo order rather than fail the whole seed.
        }
    }
}
