<?php

namespace MoneyMonkey\Enums;

enum CollectionMethod: string
{
    case WebFetch = 'web_fetch';
    case WebSearch = 'web_search';
    case Api = 'api';
    case Derived = 'derived';
    case NotFound = 'not_found';
}

