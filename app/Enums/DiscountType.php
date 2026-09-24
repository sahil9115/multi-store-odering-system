<?php

namespace App\Enums;

enum DiscountType: string
{
    case None = 'none';
    case Product = 'product';
    case Platform = 'platform';
}
