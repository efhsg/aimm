<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Collection status indicating the overall result of a collection run.
 */
enum CollectionStatus: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
