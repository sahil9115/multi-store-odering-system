<?php

namespace App\Enums;

enum MovementType: string
{
    case Decrement = 'decrement';
    case Increment = 'increment';
    case Adjustment = 'adjustment';
}
