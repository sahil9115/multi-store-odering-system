<?php

namespace App\Livewire\Checkout;

use App\Domain\Ordering\CheckoutException;
use App\Domain\Ordering\OrderPlacer;
use App\Models\Address;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $selectedAddressId = null;

    public bool $confirmedAdjustments = false;

    public function mount(): void
    {
        $this->selectedAddressId = auth()->user()->addresses()->where('is_default', true)->value('id')
            ?? auth()->user()->addresses()->value('id');
    }

    public function updatedSelectedAddressId(): void
    {
        $this->confirmedAdjustments = false;
    }

    protected function selectedAddress(): ?Address
    {
        if (! $this->selectedAddressId) {
            return null;
        }

        return auth()->user()->addresses()->whereKey($this->selectedAddressId)->first();
    }

    public function placeOrder(OrderPlacer $orderPlacer)
    {
        $address = $this->selectedAddress();

        if (! $address) {
            $this->addError('address', 'Please select a delivery address.');

            return;
        }

        if (! $address->hasCoordinates()) {
            $this->addError('address', 'That address is missing coordinates. Edit it before checking out.');

            return;
        }

        $preview = $orderPlacer->preview(auth()->user()->currentCart(), $address);

        if (! $this->confirmedAdjustments && $preview->hasShortfall()) {
            // Surface the adjustment for confirmation instead of silently placing the order.
            $this->confirmedAdjustments = false;

            return;
        }

        try {
            $order = $orderPlacer->place(auth()->user(), auth()->user()->currentCart(), $address);
        } catch (CheckoutException $exception) {
            $this->addError('checkout', $exception->getMessage());

            return;
        }

        session()->flash('status', "Order {$order->order_number} placed successfully.");

        return $this->redirect(route('orders.index'), navigate: true);
    }

    public function render(OrderPlacer $orderPlacer)
    {
        $addresses = auth()->user()->addresses()->orderByDesc('is_default')->get();
        $address = $this->selectedAddress();

        $preview = $address
            ? $orderPlacer->preview(auth()->user()->currentCart(), $address)
            : null;

        return view('livewire.checkout.index', [
            'addresses' => $addresses,
            'preview' => $preview,
        ]);
    }
}
