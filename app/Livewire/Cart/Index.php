<?php

namespace App\Livewire\Cart;

use App\Domain\Ordering\CartLine;
use App\Domain\Ordering\DiscountEvaluator;
use App\Enums\ProductStatus;
use App\Models\CartItem;
use Brick\Money\Money;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = $this->ownedItem($itemId);

        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(int $itemId): void
    {
        $this->ownedItem($itemId)->delete();
    }

    protected function ownedItem(int $itemId): CartItem
    {
        return auth()->user()->currentCart()->items()->whereKey($itemId)->firstOrFail();
    }

    public function render(DiscountEvaluator $evaluator)
    {
        $cart = auth()->user()->currentCart();
        $items = $cart->items()->with('product')->get();

        $currency = config('shop.currency');
        $lines = $items
            ->map(fn (CartItem $item) => new CartLine($item->product_id, $item->quantity, Money::of($item->unit_price, $currency)))
            ->values()
            ->all();
        $result = $evaluator->evaluate($lines, $currency);

        $unavailableProductIds = $items
            ->filter(fn (CartItem $item) => ! $item->product || $item->product->status !== ProductStatus::Active)
            ->pluck('product_id');

        return view('livewire.cart.index', [
            'items' => $items,
            'result' => $result,
            'lineDiscounts' => $result->lineDiscounts,
            'hasUnavailableItems' => $unavailableProductIds->isNotEmpty(),
        ]);
    }
}
