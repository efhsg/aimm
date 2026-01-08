<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Business fundamentals assessment based on YoY trend analysis.
 */
enum Fundamentals: string
{
    case Improving = 'improving';
    case Mixed = 'mixed';
    case Deteriorating = 'deteriorating';
}
