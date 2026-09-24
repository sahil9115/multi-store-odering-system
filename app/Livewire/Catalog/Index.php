<?php

namespace App\Livewire\Catalog;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function addToCart(int $productId, int $quantity = 1): void
    {
        $product = Product::query()->active()->findOrFail($productId);

        $quantity = max(1, $quantity);

        $cart = auth()->user()->currentCart();

        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $item->unit_price = $product->price;
        $item->save();

        $this->dispatch('cart-updated');

        session()->flash('status', "Added {$quantity} x {$product->name} to your cart.");
    }

    public function render()
    {
        $products = Product::query()
            ->active()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.catalog.index', ['products' => $products]);
    }
}
