<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_admin_area(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customers_cannot_access_the_admin_area(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_admins_can_access_the_admin_area(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_admins_are_redirected_from_the_generic_dashboard_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(UserRole::Admin->value);

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin');
    }
}
