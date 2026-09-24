<div>
    <x-slot name="header">
        <x-page-heading subtitle="Track your past orders and their delivery status.">
            My Orders
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 border border-green-100 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @error('return')
                <div class="rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
            @enderror

            @if ($orders->isEmpty())
                <x-panel>
                    <x-empty-state title="No orders yet" description="Once you place an order, it will show up here.">
                        <x-slot name="action">
                            <a href="{{ route('catalog.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-amber-600 rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-amber-500">
                                Start shopping
                            </a>
                        </x-slot>
                    </x-empty-state>
                </x-panel>
            @else
                <div class="space-y-4">
                    @foreach ($orders as $order)
                        <x-panel class="overflow-hidden">
                            <div class="px-5 py-4 flex justify-between items-start bg-gray-50/60 border-b border-gray-100">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $order->order_number }}</p>
                                    <p class="text-sm text-gray-500">{{ $order->placed_at->format('M j, Y g:ia') }}</p>
                                </div>
                                <div class="text-right">
                                    <x-badge :color="match($order->status->value) {
                                        'confirmed', 'fulfilled' => 'green',
                                        'pending', 'processing' => 'yellow',
                                        'cancelled' => 'red',
                                        default => 'gray',
                                    }">
                                        {{ ucfirst($order->status->value) }}
                                    </x-badge>
                                    <p class="font-bold text-lg text-gray-900 mt-1">${{ $order->total }}</p>
                                </div>
                            </div>

                            <div class="divide-y divide-gray-100">
                                @php $orderIsReturnable = in_array($order->status->value, ['confirmed', 'processing', 'fulfilled'], true); @endphp
                                @foreach ($order->items as $item)
                                    @php $remaining = $item->remainingQuantity(); @endphp
                                    <div class="px-5 py-3">
                                        <div class="flex justify-between items-start gap-4">
                                            <div class="text-sm">
                                                <p class="text-gray-900">
                                                    {{ $item->product_name_snapshot }} × {{ $item->quantity }}
                                                    @if ($item->returned_quantity > 0)
                                                        <span class="text-gray-500">({{ $item->returned_quantity }} returned)</span>
                                                    @endif
                                                </p>
                                                @if ($item->fulfillment_status->value !== 'allocated')
                                                    <x-badge :color="$item->fulfillment_status->value === 'returned' ? 'gray' : 'yellow'">
                                                        {{ $item->fulfillment_status->value }}
                                                    </x-badge>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-medium text-gray-900">${{ $item->line_total }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    @foreach ($item->allocations as $allocation)
                                                        {{ $allocation->quantity_allocated }} × {{ $allocation->store->name }}@if (! $loop->last), @endif
                                                    @endforeach
                                                </p>
                                            </div>
                                        </div>

                                        @if ($orderIsReturnable && $remaining > 0)
                                            <div class="mt-2 flex items-center gap-2" x-data="{ qty: 1, open: false }">
                                                <button type="button" @click="open = ! open" class="text-xs font-medium text-amber-600 hover:text-amber-500">
                                                    Return item
                                                </button>
                                                <template x-if="open">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex items-center border border-gray-300 rounded-lg bg-white">
                                                            <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-gray-700">−</button>
                                                            <input type="number" min="1" max="{{ $remaining }}" x-model.number="qty" class="w-10 border-0 text-xs text-center focus:ring-0 p-0" />
                                                            <button type="button" @click="qty = Math.min({{ $remaining }}, qty + 1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-gray-700">+</button>
                                                        </div>
                                                        <span class="text-xs text-gray-400">of {{ $remaining }} eligible</span>
                                                        <button
                                                            type="button"
                                                            wire:click="returnItem({{ $item->id }}, qty)"
                                                            wire:confirm="Return this quantity and restock it to the originating store?"
                                                            class="text-xs font-semibold text-red-600 hover:text-red-500"
                                                        >
                                                            Confirm return
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </x-panel>
                    @endforeach
                </div>

                <div>{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</div>
