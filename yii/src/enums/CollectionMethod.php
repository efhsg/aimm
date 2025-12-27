<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Collection method indicating how a datapoint was obtained.
 */
enum CollectionMethod: string
{
    case WebFetch = 'web_fetch';
    case WebSearch = 'web_search';
    case Api = 'api';
    case Cache = 'cache';
    case Derived = 'derived';
    case NotFound = 'not_found';
}
