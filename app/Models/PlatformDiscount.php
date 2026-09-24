<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'min_order_amount', 'discount_percent', 'starts_at', 'ends_at', 'is_active', 'priority'])]
class PlatformDiscount extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_order_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
