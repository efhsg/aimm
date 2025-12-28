<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Scale of numeric monetary values.
 */
enum DataScale: string
{
    case Units = 'units';
    case Thousands = 'thousands';
    case Millions = 'millions';
    case Billions = 'billions';
    case Trillions = 'trillions';
}
