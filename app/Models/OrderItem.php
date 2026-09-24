<?php

namespace App\Models;

use App\Enums\OrderItemFulfillmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'product_id', 'product_name_snapshot', 'quantity', 'returned_quantity', 'unit_price',
    'line_subtotal', 'product_discount_percent', 'line_discount_amount', 'line_total',
    'fulfillment_status',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fulfillment_status' => OrderItemFulfillmentStatus::class,
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'product_discount_percent' => 'decimal:2',
            'line_discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderItemAllocation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function allocatedQuantity(): int
    {
        return (int) $this->allocations->sum('quantity_allocated');
    }

    /**
     * How much of what was actually delivered is still with the customer (not yet returned).
     * This is the basis for both "how much can still be returned" and discount recalculation
     * after a return — not the original requested quantity, which may exceed what was fulfilled.
     */
    public function remainingQuantity(): int
    {
        return $this->allocatedQuantity() - $this->returned_quantity;
    }
}
