<?php

namespace App\Domain\Ordering;

final readonly class CheckoutPreview
{
    /**
     * @param  array<int, PreviewLine>  $lines
     */
    public function __construct(
        public array $lines,
        public DiscountResult $discountResult,
    ) {}

    public function hasShortfall(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->isShortfall()) {
                return true;
            }
        }

        return false;
    }

    public function isFullyUnavailable(): bool
    {
        if ($this->lines === []) {
            return true;
        }

        foreach ($this->lines as $line) {
            if (! $line->isUnavailable()) {
                return false;
            }
        }

        return true;
    }
}
