<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'address_line', 'lat', 'lng', 'phone', 'status'])]
class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StoreStatus::Active);
    }

    /**
     * Rank stores that currently stock a product, nearest first, by Haversine distance to a given point.
     */
    public function scopeNearestTo(Builder $query, float $lat, float $lng, int $productId): Builder
    {
        return $query
            ->select('stores.*')
            ->addSelect('store_product.id as store_product_id')
            ->addSelect('store_product.quantity_on_hand as available_quantity')
            ->selectRaw(
                '(6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(stores.lat)) * COS(RADIANS(stores.lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(stores.lat)))) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->join('store_product', 'store_product.store_id', '=', 'stores.id')
            ->where('store_product.product_id', $productId)
            ->where('store_product.quantity_on_hand', '>', 0)
            ->where('stores.status', StoreStatus::Active)
            ->orderBy('distance_km')
            ->orderBy('stores.id');
    }
}
