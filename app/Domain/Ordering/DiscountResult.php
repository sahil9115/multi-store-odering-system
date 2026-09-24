<?php

namespace App\Domain\Ordering;

use App\Enums\DiscountType;
use Brick\Money\Money;

final readonly class DiscountResult
{
    /**
     * @param  array<int, Money>  $lineDiscounts  Per-line product-discount amounts, keyed by the
     *                                            CartLine's position in the input array. Populated
     *                                            even when the platform discount ends up winning,
     *                                            so the caller can see what each side computed.
     */
    public function __construct(
        public Money $subtotal,
        public DiscountType $discountType,
        public Money $discountAmount,
        public Money $total,
        public array $lineDiscounts,
    ) {}

    /**
     * The discount actually applied to a line's persisted total. Product and platform
     * discounts are mutually exclusive, so this is zero unless the product discount won.
     */
    public function appliedLineDiscount(int $index): Money
    {
        if ($this->discountType !== DiscountType::Product) {
            return Money::zero($this->subtotal->getCurrency());
        }

        return $this->lineDiscounts[$index] ?? Money::zero($this->subtotal->getCurrency());
    }
}
