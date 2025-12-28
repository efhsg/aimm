<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Locator types for provenance snippets.
 */
enum SourceLocatorType: string
{
    case Html = 'html';
    case Json = 'json';
    case Xpath = 'xpath';
}
