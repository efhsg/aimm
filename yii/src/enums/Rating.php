<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Investment recommendation rating.
 */
enum Rating: string
{
    case Buy = 'buy';
    case Hold = 'hold';
    case Sell = 'sell';
}
