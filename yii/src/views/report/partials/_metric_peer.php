<?php

declare(strict_types=1);

/**
 * Format a metric's peer average value.
 *
 * @var object $metric Metric with peerAverage and format properties
 */

echo $this->render('_format_value', [
    'value' => $metric->peerAverage ?? null,
    'format' => $metric->format ?? 'number',
]);
