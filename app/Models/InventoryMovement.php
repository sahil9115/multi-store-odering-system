<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_product_id', 'order_item_allocation_id', 'type', 'quantity_delta', 'balance_after', 'reason', 'created_by'])]
class InventoryMovement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
        ];
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
