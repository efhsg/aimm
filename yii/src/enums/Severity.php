<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Severity level for datapoint requirements.
 */
enum Severity: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Optional = 'optional';
}
