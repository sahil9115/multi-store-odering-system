<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_item_id', 'store_id', 'quantity_allocated', 'returned_quantity', 'unit_price', 'distance_km'])]
class OrderItemAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'distance_km' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function remainingQuantity(): int
    {
        return $this->quantity_allocated - $this->returned_quantity;
    }
}
