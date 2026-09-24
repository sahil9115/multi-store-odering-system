<?php

namespace App\Enums;

enum OrderItemFulfillmentStatus: string
{
    case Pending = 'pending';
    case Allocated = 'allocated';
    case Partial = 'partial';
    case Failed = 'failed';
    case Returned = 'returned';
}
