<?php

namespace App\Domain\Ordering;

use Brick\Money\Money;

final readonly class CartLine
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public Money $unitPrice,
    ) {}

    public function subtotal(): Money
    {
        return $this->unitPrice->multipliedBy($this->quantity);
    }
}
