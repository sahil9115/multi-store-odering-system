<?php

namespace App\Models;

use App\Domain\Ordering\CartLine;
use App\Enums\CartStatus;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'status'])]
class Cart extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return array<int, CartLine>
     */
    public function toCartLines(string $currency): array
    {
        return $this->items
            ->map(fn (CartItem $item) => new CartLine(
                $item->product_id,
                $item->quantity,
                Money::of($item->unit_price, $currency)
            ))
            ->values()
            ->all();
    }
}
