<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Valuation gap direction relative to peer average.
 */
enum GapDirection: string
{
    case Undervalued = 'undervalued';
    case Fair = 'fair';
    case Overvalued = 'overvalued';
}
