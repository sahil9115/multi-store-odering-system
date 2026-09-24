<?php

namespace App\Domain\Ordering;

final readonly class PreviewLine
{
    /**
     * @param  array<int, PlannedAllocation>  $allocations
     */
    public function __construct(
        public int $productId,
        public string $productName,
        public int $requestedQuantity,
        public int $fulfillableQuantity,
        public array $allocations,
    ) {}

    public function isShortfall(): bool
    {
        return $this->fulfillableQuantity < $this->requestedQuantity;
    }

    public function isUnavailable(): bool
    {
        return $this->fulfillableQuantity === 0;
    }
}
