<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    public function test_admin_can_view_the_order_list_and_a_single_order_with_its_allocations(): void
    {
        $order = Order::factory()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id]);
        $store = Store::factory()->create();
        OrderItemAllocation::create([
            'order_item_id' => $item->id,
            'store_id' => $store->id,
            'quantity_allocated' => $item->quantity,
            'unit_price' => $item->unit_price,
            'distance_km' => 3.4,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(OrderResource\Pages\ListOrders::class)
            ->assertOk()
            ->assertSee($order->order_number);

        Livewire::test(OrderResource\Pages\ViewOrder::class, ['record' => $order->id])
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_customers_cannot_access_the_order_admin_resource(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)
            ->get(OrderResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_empty_order_list_renders_without_error(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(OrderResource\Pages\ListOrders::class)->assertOk();
    }
}
