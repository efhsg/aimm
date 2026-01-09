<?php

declare(strict_types=1);

/**
 * Format a metric's YoY change value.
 *
 * @var object $metric Metric with change property
 */

$change = $metric->change ?? null;

if ($change === null) {
    echo '-';
    return;
}

$sign = $change >= 0 ? '+' : '';
echo $sign . number_format($change * 100, 1) . '%';
