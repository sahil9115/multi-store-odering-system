<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\PlatformDiscountResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StoreResource;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    public function test_admin_can_render_the_store_list_and_create_a_store(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(StoreResource\Pages\ListStores::class)->assertOk();

        Livewire::test(StoreResource\Pages\CreateStore::class)
            ->fillForm([
                'name' => 'Downtown',
                'code' => 'DTN-001',
                'address_line' => '123 Main St',
                'lat' => 40.7128,
                'lng' => -74.0060,
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stores', ['code' => 'DTN-001']);
    }

    public function test_store_lat_lng_out_of_range_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(StoreResource\Pages\CreateStore::class)
            ->fillForm([
                'name' => 'Bad Store',
                'code' => 'BAD-001',
                'address_line' => '1 Nowhere',
                'lat' => 200,
                'lng' => -74.0060,
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['lat']);
    }

    public function test_store_code_must_be_unique(): void
    {
        Store::factory()->create(['code' => 'DUPE-001']);

        $this->actingAs($this->admin());

        Livewire::test(StoreResource\Pages\CreateStore::class)
            ->fillForm([
                'name' => 'Another Store',
                'code' => 'DUPE-001',
                'address_line' => '1 Nowhere',
                'lat' => 40.7128,
                'lng' => -74.0060,
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    public function test_admin_can_render_the_product_list_and_create_a_product(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ProductResource\Pages\ListProducts::class)->assertOk();

        Livewire::test(ProductResource\Pages\CreateProduct::class)
            ->fillForm([
                'name' => 'Widget',
                'slug' => 'widget',
                'sku' => 'WID-001',
                'price' => 19.99,
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['sku' => 'WID-001']);
    }

    public function test_product_sku_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'DUPE-SKU']);

        $this->actingAs($this->admin());

        Livewire::test(ProductResource\Pages\CreateProduct::class)
            ->fillForm([
                'name' => 'Another Widget',
                'slug' => 'another-widget',
                'sku' => 'DUPE-SKU',
                'price' => 9.99,
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    public function test_admin_can_render_the_product_edit_page_with_inventory_and_discount_relation_managers(): void
    {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $product->storeProducts()->create(['store_id' => $store->id, 'quantity_on_hand' => 10]);
        ProductDiscount::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->admin());

        Livewire::test(ProductResource\Pages\EditProduct::class, ['record' => $product->id])->assertOk();
    }

    public function test_admin_can_render_platform_discounts_and_create_one(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PlatformDiscountResource\Pages\ListPlatformDiscounts::class)->assertOk();

        Livewire::test(PlatformDiscountResource\Pages\CreatePlatformDiscount::class)
            ->fillForm([
                'name' => 'Big Order',
                'min_order_amount' => 100,
                'discount_percent' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('platform_discounts', ['name' => 'Big Order']);
    }

    public function test_empty_store_and_product_lists_render_without_error(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(StoreResource\Pages\ListStores::class)->assertOk();
        Livewire::test(ProductResource\Pages\ListProducts::class)->assertOk();
        Livewire::test(PlatformDiscountResource\Pages\ListPlatformDiscounts::class)->assertOk();
    }
}
