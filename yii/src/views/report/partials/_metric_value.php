<?php

declare(strict_types=1);

/**
 * Format a metric's current value.
 *
 * @var object $metric Metric with value and format properties
 */

echo $this->render('_format_value', [
    'value' => $metric->value ?? null,
    'format' => $metric->format ?? 'number',
]);
