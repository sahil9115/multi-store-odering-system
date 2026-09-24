<?php

namespace App\Domain\Ordering;

final readonly class PlannedAllocation
{
    public function __construct(
        public int $storeId,
        public string $storeName,
        public int $quantity,
        public ?float $distanceKm,
    ) {}
}
