<div>
    <x-slot name="header">
        <x-page-heading subtitle="Review your items before checking out.">
            Your Cart
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">
            @if ($hasUnavailableItems)
                <div class="rounded-lg bg-yellow-50 border border-yellow-100 px-4 py-3 text-sm text-yellow-800">
                    One or more items in your cart are no longer available and will be removed at checkout.
                </div>
            @endif

            @if ($items->isEmpty())
                <x-panel>
                    <x-empty-state
                        title="Your cart is empty"
                        description="Browse the shop to add something."
                        :icon="'<svg class=&quot;h-6 w-6&quot; fill=&quot;none&quot; viewBox=&quot;0 0 24 24&quot; stroke=&quot;currentColor&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.887-4.788 2.174-7.398.075-.676-.462-1.262-1.142-1.262H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z&quot; /></svg>'"
                    >
                        <x-slot name="action">
                            <a href="{{ route('catalog.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-amber-600 rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-amber-500">
                                Browse products
                            </a>
                        </x-slot>
                    </x-empty-state>
                </x-panel>
            @else
                <x-panel class="divide-y divide-gray-100">
                    @foreach ($items as $index => $item)
                        <div class="p-5 flex items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900 truncate">
                                    {{ $item->product?->name ?? 'Unavailable product' }}
                                    @if (! $item->product || $item->product->status->value !== 'active')
                                        <x-badge color="red">unavailable</x-badge>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500">${{ number_format($item->unit_price, 2) }} each</p>

                                @php $lineDiscount = $result->appliedLineDiscount($index); @endphp
                                @if (! $lineDiscount->isZero())
                                    <p class="text-xs text-green-700 mt-0.5">-${{ $lineDiscount->getAmount() }} discount applied</p>
                                @endif
                            </div>

                            <input
                                type="number"
                                min="0"
                                value="{{ $item->quantity }}"
                                wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                class="w-16 rounded-lg border-gray-300 shadow-sm text-sm text-center"
                            />

                            <p class="w-20 text-right font-semibold text-gray-900">${{ number_format($item->unit_price * $item->quantity, 2) }}</p>

                            <button wire:click="removeItem({{ $item->id }})" type="button" class="text-gray-400 hover:text-red-600 transition" title="Remove">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </x-panel>

                <x-panel class="p-5 space-y-2">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Subtotal</span>
                        <span>${{ $result->subtotal->getAmount() }}</span>
                    </div>
                    @if ($result->discountType->value !== 'none')
                        <div class="flex justify-between text-sm text-green-700">
                            <span>Discount ({{ $result->discountType->value === 'product' ? 'quantity discount' : 'order discount' }})</span>
                            <span>-${{ $result->discountAmount->getAmount() }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between font-bold text-lg text-gray-900 border-t border-gray-100 pt-3 mt-1">
                        <span>Total</span>
                        <span>${{ $result->total->getAmount() }}</span>
                    </div>

                    <div class="pt-3 text-right">
                        <a
                            href="{{ route('checkout.index') }}"
                            wire:navigate
                            class="inline-flex justify-center items-center gap-1.5 px-5 py-2.5 bg-amber-600 rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-amber-500 transition"
                        >
                            Proceed to checkout
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </x-panel>
            @endif
        </div>
    </div>
</div>
