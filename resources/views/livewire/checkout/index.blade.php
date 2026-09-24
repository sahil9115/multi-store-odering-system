<div>
    <x-slot name="header">
        <x-page-heading subtitle="Confirm your delivery address and place your order.">
            Checkout
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-5">
            @error('checkout')
                <div class="rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
            @enderror

            <x-panel class="p-5 space-y-3">
                <h3 class="font-semibold text-gray-900">Delivery address</h3>

                @if ($addresses->isEmpty())
                    <p class="text-sm text-gray-500">
                        You have no saved addresses.
                        <a href="{{ route('addresses.index') }}" wire:navigate class="text-amber-600 font-medium hover:underline">Add one</a>
                    </p>
                @else
                    <select wire:model.live="selectedAddressId" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-amber-500 focus:ring-amber-500">
                        @foreach ($addresses as $address)
                            <option value="{{ $address->id }}">
                                {{ $address->label }} — {{ $address->line1 }}, {{ $address->city }}
                                @unless ($address->hasCoordinates()) (missing coordinates) @endunless
                            </option>
                        @endforeach
                    </select>
                @endif
                @error('address')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </x-panel>

            @if ($preview)
                @if ($preview->isFullyUnavailable())
                    <div class="rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">
                        None of the items in your cart are currently available for delivery to this address.
                    </div>
                @else
                    <x-panel class="divide-y divide-gray-100">
                        <div class="px-5 py-3 bg-gray-50/60 rounded-t-xl">
                            <h3 class="font-semibold text-gray-900 text-sm">Order items</h3>
                        </div>
                        @foreach ($preview->lines as $line)
                            <div class="p-5 flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">
                                        {{ $line->productName }}
                                        @if ($line->isUnavailable())
                                            <x-badge color="red">out of stock</x-badge>
                                        @elseif ($line->isShortfall())
                                            <x-badge color="yellow">adjusted</x-badge>
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-500">Requested {{ $line->requestedQuantity }}</p>
                                </div>
                                <div class="text-sm text-right text-gray-600 space-y-0.5">
                                    @forelse ($line->allocations as $allocation)
                                        <div>{{ $allocation->quantity }} × {{ $allocation->storeName }}
                                            @if ($allocation->distanceKm !== null)
                                                <span class="text-gray-400">({{ $allocation->distanceKm }} km)</span>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-red-600">none available</span>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </x-panel>

                    @if ($preview->hasShortfall())
                        <div class="rounded-lg bg-yellow-50 border border-yellow-100 px-4 py-3 text-sm text-yellow-800 space-y-2">
                            <p>Some items can't be fully fulfilled right now — your order will be placed with the adjusted quantities shown above.</p>
                            <label class="flex items-center gap-2 font-medium">
                                <input type="checkbox" wire:model="confirmedAdjustments" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                                I understand and want to proceed with the adjusted order.
                            </label>
                        </div>
                    @endif

                    <x-panel class="p-5 space-y-2">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Subtotal</span>
                            <span>${{ $preview->discountResult->subtotal->getAmount() }}</span>
                        </div>
                        @if ($preview->discountResult->discountType->value !== 'none')
                            <div class="flex justify-between text-sm text-green-700">
                                <span>Discount</span>
                                <span>-${{ $preview->discountResult->discountAmount->getAmount() }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-bold text-lg text-gray-900 border-t border-gray-100 pt-3 mt-1">
                            <span>Total</span>
                            <span>${{ $preview->discountResult->total->getAmount() }}</span>
                        </div>

                        <div class="pt-3 text-right">
                            <button
                                wire:click="placeOrder"
                                wire:loading.attr="disabled"
                                type="button"
                                @if ($preview->hasShortfall() && ! $confirmedAdjustments) disabled @endif
                                class="inline-flex justify-center items-center gap-1.5 px-5 py-2.5 bg-amber-600 rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            >
                                <span wire:loading.remove wire:target="placeOrder">Place order</span>
                                <span wire:loading wire:target="placeOrder">Placing order…</span>
                            </button>
                        </div>
                    </x-panel>
                @endif
            @endif
        </div>
    </div>
</div>
