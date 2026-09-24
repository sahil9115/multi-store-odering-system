<?php

namespace App\Livewire\Addresses;

use App\Models\Address;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $editingId = null;

    public string $label = '';

    public string $line1 = '';

    public string $line2 = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public string $country = '';

    public ?float $lat = null;

    public ?float $lng = null;

    public bool $is_default = false;

    public bool $showForm = false;

    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'lng' => ['nullable', 'numeric', 'min:-180', 'max:180'],
            'is_default' => ['boolean'],
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'label', 'line1', 'line2', 'city', 'state', 'postal_code', 'country', 'lat', 'lng', 'is_default']);
        $this->showForm = true;
    }

    public function edit(int $addressId): void
    {
        $address = $this->ownedAddress($addressId);

        $this->editingId = $address->id;
        $this->label = $address->label;
        $this->line1 = $address->line1;
        $this->line2 = (string) $address->line2;
        $this->city = $address->city;
        $this->state = (string) $address->state;
        $this->postal_code = (string) $address->postal_code;
        $this->country = $address->country;
        $this->lat = $address->lat ? (float) $address->lat : null;
        $this->lng = $address->lng ? (float) $address->lng : null;
        $this->is_default = $address->is_default;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $address = $this->editingId
            ? $this->ownedAddress($this->editingId)
            : new Address(['user_id' => auth()->id()]);

        $address->fill($data);
        $address->save();

        if ($data['is_default']) {
            auth()->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        $this->showForm = false;
    }

    public function delete(int $addressId): void
    {
        $this->ownedAddress($addressId)->delete();
    }

    public function makeDefault(int $addressId): void
    {
        $address = $this->ownedAddress($addressId);

        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    protected function ownedAddress(int $addressId): Address
    {
        return auth()->user()->addresses()->whereKey($addressId)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.addresses.index', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->get(),
        ]);
    }
}
