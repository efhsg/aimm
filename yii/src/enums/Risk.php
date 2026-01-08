<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Financial risk assessment based on balance sheet ratios.
 */
enum Risk: string
{
    case Acceptable = 'acceptable';
    case Elevated = 'elevated';
    case Unacceptable = 'unacceptable';
}
