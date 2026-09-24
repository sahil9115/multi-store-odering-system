<div>
    <x-slot name="header">
        <x-page-heading subtitle="Browse what's available and add items to your cart.">
            Shop
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 border border-green-100 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="relative max-w-md">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search products..."
                    class="w-full rounded-lg border-gray-300 pl-9 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                />
            </div>

            @if ($products->isEmpty())
                <x-panel>
                    <x-empty-state
                        title="No products found"
                        description="Try a different search, or check back later."
                        :icon="'<svg class=&quot;h-6 w-6&quot; fill=&quot;none&quot; viewBox=&quot;0 0 24 24&quot; stroke=&quot;currentColor&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z&quot; /></svg>'"
                    />
                </x-panel>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($products as $product)
                        <x-panel class="flex flex-col overflow-hidden hover:shadow-md transition-shadow" x-data="{ qty: 1 }">
                            <div class="p-5 flex-1 flex flex-col">
                                <h3 class="font-semibold text-gray-900">{{ $product->name }}</h3>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2 flex-1">{{ $product->description }}</p>
                                <p class="text-xl font-bold text-gray-900 mt-3">${{ number_format($product->price, 2) }}</p>
                            </div>

                            <div class="border-t border-gray-100 p-4 flex items-center gap-2 bg-gray-50/50">
                                <div class="flex items-center border border-gray-300 rounded-lg bg-white shrink-0">
                                    <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-8 h-9 flex items-center justify-center text-gray-500 hover:text-gray-700">−</button>
                                    <input type="number" min="1" x-model.number="qty" class="w-10 border-0 text-sm text-center focus:ring-0 p-0" />
                                    <button type="button" @click="qty++" class="w-8 h-9 flex items-center justify-center text-gray-500 hover:text-gray-700">+</button>
                                </div>
                                <button
                                    @click="$wire.addToCart({{ $product->id }}, qty)"
                                    type="button"
                                    class="flex-1 inline-flex justify-center items-center gap-1.5 px-3 py-2 bg-amber-600 rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-amber-500 transition"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.887-4.788 2.174-7.398.075-.676-.462-1.262-1.142-1.262H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                                    </svg>
                                    Add to cart
                                </button>
                            </div>
                        </x-panel>
                    @endforeach
                </div>

                <div>{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
