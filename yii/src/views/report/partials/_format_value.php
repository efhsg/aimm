<?php

declare(strict_types=1);

/**
 * Shared value formatting partial.
 *
 * Formats numeric values based on format type (currency, percent, number).
 * Centralizes formatting logic to avoid DRY violations across metric partials.
 *
 * @var float|null $value The numeric value to format
 * @var string $format The format type: 'currency', 'percent', or 'number'
 */

if ($value === null) {
    echo '-';
    return;
}

echo match ($format) {
    'currency' => '$' . number_format($value / 1_000_000, 1) . 'M',
    'percent' => number_format($value * 100, 1) . '%',
    default => number_format($value, 2),
};
