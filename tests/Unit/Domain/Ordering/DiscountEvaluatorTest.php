<?php

namespace Tests\Unit\Domain\Ordering;

use App\Domain\Ordering\CartLine;
use App\Domain\Ordering\DiscountEvaluator;
use App\Enums\DiscountType;
use App\Models\PlatformDiscount;
use App\Models\Product;
use App\Models\ProductDiscount;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected DiscountEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new DiscountEvaluator;
    }

    protected function line(int $productId, int $quantity, string $unitPrice): CartLine
    {
        return new CartLine($productId, $quantity, Money::of($unitPrice, 'USD'));
    }

    public function test_no_discounts_configured_yields_none(): void
    {
        $product = Product::factory()->create();

        $result = $this->evaluator->evaluate([$this->line($product->id, 1, '10.00')], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
        $this->assertTrue($result->discountAmount->isZero());
        $this->assertTrue($result->total->isEqualTo(Money::of('10.00', 'USD')));
    }

    public function test_product_discount_applies_exactly_at_the_minimum_quantity_threshold(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 5,
            'discount_percent' => 10,
        ]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 5, '10.00')], 'USD');

        $this->assertSame(DiscountType::Product, $result->discountType);
        $this->assertTrue($result->discountAmount->isEqualTo(Money::of('5.00', 'USD')));
    }

    public function test_product_discount_does_not_apply_one_below_the_threshold(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 5,
            'discount_percent' => 10,
        ]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 4, '10.00')], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
    }

    public function test_overlapping_tiers_pick_the_highest_qualifying_tier(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 5, 'discount_percent' => 10]);
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 10, 'discount_percent' => 20]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 12, '10.00')], 'USD');

        // 12 * 10.00 = 120.00, at the 20% tier = 24.00 (not the 10% tier's 12.00)
        $this->assertTrue($result->discountAmount->isEqualTo(Money::of('24.00', 'USD')));
    }

    public function test_zero_percent_discount_yields_no_discount(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 1, 'discount_percent' => 0]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 3, '10.00')], 'USD');

        $this->assertTrue($result->discountAmount->isZero());
    }

    public function test_hundred_percent_discount_zeroes_the_total(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 1, 'discount_percent' => 100]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 2, '10.00')], 'USD');

        $this->assertTrue($result->total->isZero());
    }

    public function test_expired_product_discount_is_ignored(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 1,
            'discount_percent' => 50,
            'ends_at' => now()->subDay(),
        ]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 1, '10.00')], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
    }

    public function test_future_dated_product_discount_is_ignored(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 1,
            'discount_percent' => 50,
            'starts_at' => now()->addDay(),
        ]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 1, '10.00')], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
    }

    public function test_inactive_product_discount_is_ignored(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 1,
            'discount_percent' => 50,
            'is_active' => false,
        ]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 1, '10.00')], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
    }

    public function test_platform_discount_applies_when_no_product_discount_exists(): void
    {
        $product = Product::factory()->create();
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 10]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 10, '10.00')], 'USD');

        $this->assertSame(DiscountType::Platform, $result->discountType);
        $this->assertTrue($result->discountAmount->isEqualTo(Money::of('10.00', 'USD')));
    }

    public function test_platform_discount_does_not_apply_below_its_minimum_order_amount(): void
    {
        $product = Product::factory()->create();
        PlatformDiscount::factory()->create(['min_order_amount' => 200, 'discount_percent' => 10]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 10, '10.00')], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
    }

    public function test_larger_discount_wins_when_platform_beats_product(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 1, 'discount_percent' => 5]);
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 30]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 10, '10.00')], 'USD');

        $this->assertSame(DiscountType::Platform, $result->discountType);
        $this->assertTrue($result->discountAmount->isEqualTo(Money::of('30.00', 'USD')));
        // Mutually exclusive: the product discount is never added on top.
        $this->assertTrue($result->total->isEqualTo(Money::of('70.00', 'USD')));
    }

    public function test_larger_discount_wins_when_product_beats_platform(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 1, 'discount_percent' => 40]);
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 5]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 10, '10.00')], 'USD');

        $this->assertSame(DiscountType::Product, $result->discountType);
        $this->assertTrue($result->discountAmount->isEqualTo(Money::of('40.00', 'USD')));
    }

    public function test_exact_tie_between_product_and_platform_discount_favors_product(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 1, 'discount_percent' => 10]);
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 10]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 10, '10.00')], 'USD');

        $this->assertSame(DiscountType::Product, $result->discountType);
    }

    public function test_applied_line_discount_is_zero_for_all_lines_when_platform_wins(): void
    {
        $product = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $product->id, 'min_quantity' => 1, 'discount_percent' => 5]);
        PlatformDiscount::factory()->create(['min_order_amount' => 50, 'discount_percent' => 30]);

        $result = $this->evaluator->evaluate([$this->line($product->id, 10, '10.00')], 'USD');

        $this->assertTrue($result->appliedLineDiscount(0)->isZero());
    }

    public function test_multiple_lines_only_the_qualifying_product_line_is_discounted(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        ProductDiscount::factory()->create(['product_id' => $productA->id, 'min_quantity' => 5, 'discount_percent' => 10]);

        $result = $this->evaluator->evaluate([
            $this->line($productA->id, 5, '10.00'),
            $this->line($productB->id, 1, '10.00'),
        ], 'USD');

        $this->assertTrue($result->appliedLineDiscount(0)->isEqualTo(Money::of('5.00', 'USD')));
        $this->assertTrue($result->appliedLineDiscount(1)->isZero());
    }

    public function test_empty_cart_yields_zero_totals(): void
    {
        $result = $this->evaluator->evaluate([], 'USD');

        $this->assertSame(DiscountType::None, $result->discountType);
        $this->assertTrue($result->subtotal->isZero());
        $this->assertTrue($result->total->isZero());
    }
}
