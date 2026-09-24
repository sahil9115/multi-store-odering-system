<x-app-layout>
    <x-slot name="header">
        <x-page-heading subtitle="Manage your account information and security.">
            Profile
        </x-page-heading>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-5">
            <x-panel class="p-6">
                <livewire:profile.update-profile-information-form />
            </x-panel>

            <x-panel class="p-6">
                <livewire:profile.update-password-form />
            </x-panel>

            <x-panel class="p-6">
                <livewire:profile.delete-user-form />
            </x-panel>
        </div>
    </div>
</x-app-layout>
