<div>
    <x-slot name="header">
        <x-page-heading subtitle="Manage where your orders get delivered.">
            <x-slot:actions>
                @unless ($showForm)
                    <button wire:click="startCreate" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-amber-500 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add address
                    </button>
                @endunless
            </x-slot:actions>
            Delivery Addresses
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-5">
            @if ($showForm)
                <x-panel class="p-6" x-data>
                    <form wire:submit="save" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="label" value="Label" />
                                <x-text-input id="label" wire:model="label" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('label')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="country" value="Country" />
                                <x-text-input id="country" wire:model="country" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('country')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="line1" value="Address line 1" />
                            <x-text-input id="line1" wire:model="line1" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('line1')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="line2" value="Address line 2 (optional)" />
                            <x-text-input id="line2" wire:model="line2" class="block mt-1 w-full" />
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="city" value="City" />
                                <x-text-input id="city" wire:model="city" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('city')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="state" value="State" />
                                <x-text-input id="state" wire:model="state" class="block mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="postal_code" value="Postal code" />
                                <x-text-input id="postal_code" wire:model="postal_code" class="block mt-1 w-full" />
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-gray-600">Coordinates are required so we can find your nearest store at checkout.</p>
                                <button
                                    type="button"
                                    @click="
                                        navigator.geolocation.getCurrentPosition(
                                            (pos) => {
                                                $wire.lat = pos.coords.latitude;
                                                $wire.lng = pos.coords.longitude;
                                            },
                                            () => alert('Could not get your location. Enter coordinates manually.')
                                        )
                                    "
                                    class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-500"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    Use my current location
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="lat" value="Latitude" />
                                    <x-text-input id="lat" type="number" step="any" wire:model="lat" class="block mt-1 w-full" />
                                    <x-input-error :messages="$errors->get('lat')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="lng" value="Longitude" />
                                    <x-text-input id="lng" type="number" step="any" wire:model="lng" class="block mt-1 w-full" />
                                    <x-input-error :messages="$errors->get('lng')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_default" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                            Set as default delivery address
                        </label>

                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                                Cancel
                            </button>
                            <x-primary-button>Save address</x-primary-button>
                        </div>
                    </form>
                </x-panel>
            @endif

            @if ($addresses->isEmpty() && ! $showForm)
                <x-panel>
                    <x-empty-state title="No addresses yet" description="Add a delivery address to start checking out." />
                </x-panel>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($addresses as $address)
                        <x-panel class="p-5 flex flex-col justify-between">
                            <div>
                                <p class="font-semibold text-gray-900 flex items-center gap-2 flex-wrap">
                                    {{ $address->label }}
                                    @if ($address->is_default)
                                        <x-badge color="green">Default</x-badge>
                                    @endif
                                    @unless ($address->hasCoordinates())
                                        <x-badge color="red">missing coordinates</x-badge>
                                    @endunless
                                </p>
                                <p class="text-sm text-gray-600 mt-1">{{ $address->line1 }}{{ $address->line2 ? ', '.$address->line2 : '' }}</p>
                                <p class="text-sm text-gray-600">{{ $address->city }}{{ $address->state ? ', '.$address->state : '' }} {{ $address->postal_code }}</p>
                                <p class="text-sm text-gray-600">{{ $address->country }}</p>
                            </div>
                            <div class="flex items-center gap-4 text-sm mt-4 pt-3 border-t border-gray-100">
                                <button wire:click="edit({{ $address->id }})" class="text-amber-600 font-medium hover:text-amber-500">Edit</button>
                                @unless ($address->is_default)
                                    <button wire:click="makeDefault({{ $address->id }})" class="text-gray-500 font-medium hover:text-gray-700">Make default</button>
                                @endunless
                                <button wire:click="delete({{ $address->id }})" wire:confirm="Delete this address?" class="text-red-600 font-medium hover:text-red-500 ml-auto">Delete</button>
                            </div>
                        </x-panel>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
