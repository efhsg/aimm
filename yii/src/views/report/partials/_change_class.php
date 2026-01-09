<?php

declare(strict_types=1);

/**
 * Return CSS class for change value coloring.
 *
 * @var float|null $change The change value
 */

if ($change === null) {
    return;
}

echo $change > 0 ? 'value-positive' : ($change < 0 ? 'value-negative' : 'value-neutral');
