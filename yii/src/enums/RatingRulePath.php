<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Audit trail for rating decisions.
 * Each path documents why a particular rating was assigned.
 */
enum RatingRulePath: string
{
    // Sell paths
    case SellFundamentals = 'SELL_FUNDAMENTALS';
    case SellRisk = 'SELL_RISK';

    // Hold paths
    case HoldInsufficientData = 'HOLD_INSUFFICIENT_DATA';
    case HoldDefault = 'HOLD_DEFAULT';

    // Buy paths
    case BuyAllConditions = 'BUY_ALL_CONDITIONS';
}
