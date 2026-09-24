<?php

namespace App\Livewire\Orders;

use App\Domain\Ordering\ReturnException;
use App\Domain\Ordering\ReturnProcessor;
use App\Models\OrderItem;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class History extends Component
{
    use WithPagination;

    public function returnItem(ReturnProcessor $returnProcessor, int $orderItemId, int $quantity): void
    {
        $orderItem = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))
            ->findOrFail($orderItemId);

        try {
            $returnProcessor->returnItem($orderItem, $quantity, auth()->user());
        } catch (ReturnException $exception) {
            $this->addError('return', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Return processed — your order total has been updated.');
    }

    public function render()
    {
        $orders = auth()->user()
            ->orders()
            ->with('items.allocations.store', 'items.returns')
            ->latest('placed_at')
            ->paginate(10);

        return view('livewire.orders.history', ['orders' => $orders]);
    }
}
