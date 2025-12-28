<?php

declare(strict_types=1);

namespace app\clients;

/**
 * Provides user agent strings for HTTP requests.
 */
interface UserAgentProviderInterface
{
    /**
     * Get a random user agent string.
     */
    public function getRandom(): string;
}
