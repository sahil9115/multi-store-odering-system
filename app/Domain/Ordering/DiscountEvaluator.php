<?php

namespace App\Domain\Ordering;

use App\Enums\DiscountType;
use App\Models\PlatformDiscount;
use App\Models\ProductDiscount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for discount math, shared by the cart's live "quote" and the
 * checkout transaction's authoritative recalculation. Product-level and platform-level
 * discounts are mutually exclusive per order: whichever yields the larger discount amount
 * wins (locked stakeholder decision); on an exact tie the product discount wins, since it
 * is the more specific of the two.
 *
 * @see OrderPlacer for how this feeds allocation.
 */
class DiscountEvaluator
{
    /**
     * @param  array<int, CartLine>  $lines
     */
    public function evaluate(array $lines, string $currency): DiscountResult
    {
        $zero = Money::zero($currency);

        if ($lines === []) {
            return new DiscountResult($zero, DiscountType::None, $zero, $zero, []);
        }

        $subtotal = array_reduce(
            $lines,
            fn (Money $carry, CartLine $line) => $carry->plus($line->subtotal()),
            $zero
        );

        [$lineDiscounts, $totalProductDiscount] = $this->evaluateProductDiscounts($lines, $currency);
        [$platformDiscount, $platformAmount] = $this->evaluateBestPlatformDiscount($subtotal, $currency);

        $productWins = $totalProductDiscount->isGreaterThanOrEqualTo($platformAmount);

        $discountType = match (true) {
            $totalProductDiscount->isZero() && $platformAmount->isZero() => DiscountType::None,
            $productWins => DiscountType::Product,
            default => DiscountType::Platform,
        };

        $discountAmount = $discountType === DiscountType::Platform ? $platformAmount : $totalProductDiscount;

        return new DiscountResult(
            subtotal: $subtotal,
            discountType: $discountType,
            discountAmount: $discountAmount,
            total: $subtotal->minus($discountAmount),
            lineDiscounts: $lineDiscounts,
        );
    }

    /**
     * @param  array<int, CartLine>  $lines
     * @return array{0: array<int, Money>, 1: Money}
     */
    protected function evaluateProductDiscounts(array $lines, string $currency): array
    {
        $productIds = array_unique(array_map(fn (CartLine $line) => $line->productId, $lines));

        $now = Carbon::now();

        $tiersByProduct = ProductDiscount::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderByDesc('min_quantity')
            ->get()
            ->groupBy('product_id');

        $lineDiscounts = [];
        $total = Money::zero($currency);

        foreach ($lines as $index => $line) {
            $tier = ($tiersByProduct->get($line->productId) ?? collect())
                ->first(fn (ProductDiscount $discount) => $line->quantity >= $discount->min_quantity);

            $lineDiscount = $tier
                ? $this->percentageOf($line->subtotal(), $tier->discount_percent)
                : Money::zero($currency);

            $lineDiscounts[$index] = $lineDiscount;
            $total = $total->plus($lineDiscount);
        }

        return [$lineDiscounts, $total];
    }

    /**
     * @return array{0: ?PlatformDiscount, 1: Money}
     */
    protected function evaluateBestPlatformDiscount(Money $subtotal, string $currency): array
    {
        $now = Carbon::now();

        $discount = PlatformDiscount::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where('min_order_amount', '<=', $subtotal->getAmount())
            ->orderByDesc('priority')
            ->orderByDesc('min_order_amount')
            ->first();

        if (! $discount) {
            return [null, Money::zero($currency)];
        }

        return [$discount, $this->percentageOf($subtotal, $discount->discount_percent)];
    }

    /**
     * The discount percent of the highest quantity tier a product qualifies for, or null if
     * none applies. Used to snapshot `product_discount_percent` on an order/order_item whenever
     * the product discount is the one that won (at placement, and again after a return).
     */
    public function productDiscountPercentFor(int $productId, int $quantity): ?string
    {
        return ProductDiscount::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->value('discount_percent');
    }

    /**
     * `$percent / 100` as a float loses precision and silently corrupts Money::multipliedBy()
     * in brick/money — the ratio must stay a string/BigDecimal all the way through.
     */
    protected function percentageOf(Money $amount, string $percent): Money
    {
        $ratio = BigDecimal::of($percent)->dividedBy(100, scale: 10, roundingMode: RoundingMode::HalfUp);

        return $amount->multipliedBy($ratio, RoundingMode::HalfUp);
    }
}
