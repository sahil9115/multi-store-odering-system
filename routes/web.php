<?php

use App\Enums\UserRole;
use App\Livewire\Addresses\Index as AddressesIndex;
use App\Livewire\Cart\Index as CartIndex;
use App\Livewire\Catalog\Index as CatalogIndex;
use App\Livewire\Checkout\Index as CheckoutIndex;
use App\Livewire\Orders\History as OrdersHistory;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', function () {
    $user = request()->user();

    if ($user->hasRole(UserRole::Admin->value)) {
        return redirect('/admin');
    }

    return view('dashboard', [
        'cartItemCount' => $user->currentCart()->items()->count(),
        'orderCount' => $user->orders()->count(),
        'recentOrders' => $user->orders()->latest('placed_at')->take(3)->get(),
    ]);
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('shop', CatalogIndex::class)->name('catalog.index');
    Route::get('cart', CartIndex::class)->name('cart.index');
    Route::get('addresses', AddressesIndex::class)->name('addresses.index');
    Route::get('checkout', CheckoutIndex::class)->name('checkout.index');
    Route::get('orders', OrdersHistory::class)->name('orders.index');
});

require __DIR__.'/auth.php';
