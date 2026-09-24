<x-app-layout>
    <x-slot name="header">
        <x-page-heading :subtitle="'Welcome back, '.auth()->user()->name.'.'">
            Dashboard
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="{{ route('cart.index') }}" wire:navigate>
                    <x-panel class="p-5 hover:shadow-md transition-shadow">
                        <p class="text-sm text-gray-500">Items in cart</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $cartItemCount }}</p>
                    </x-panel>
                </a>
                <a href="{{ route('orders.index') }}" wire:navigate>
                    <x-panel class="p-5 hover:shadow-md transition-shadow">
                        <p class="text-sm text-gray-500">Orders placed</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $orderCount }}</p>
                    </x-panel>
                </a>
                <a href="{{ route('catalog.index') }}" wire:navigate>
                    <x-panel class="p-5 hover:shadow-md transition-shadow">
                        <p class="text-sm text-gray-500">Continue shopping</p>
                        <p class="text-lg font-semibold text-amber-600 mt-1 flex items-center gap-1">
                            Browse the shop
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </p>
                    </x-panel>
                </a>
            </div>

            <x-panel>
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Recent orders</h3>
                </div>
                @if ($recentOrders->isEmpty())
                    <x-empty-state title="No orders yet" description="Your placed orders will show up here." />
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach ($recentOrders as $order)
                            <div class="px-5 py-3 flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $order->order_number }}</p>
                                    <p class="text-sm text-gray-500">{{ $order->placed_at->format('M j, Y') }}</p>
                                </div>
                                <p class="font-semibold text-gray-900">${{ $order->total }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel>
        </div>
    </div>
</x-app-layout>
