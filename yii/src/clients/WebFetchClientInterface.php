<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\FetchResult;

/**
 * Abstraction over HTTP client for fetching web pages.
 */
interface WebFetchClientInterface
{
    /**
     * Fetch content from URL.
     *
     * @throws \app\exceptions\NetworkException On connection failure
     * @throws \app\exceptions\RateLimitException When rate limited by source
     * @throws \app\exceptions\BlockedException When blocked by source (e.g., 401/403)
     */
    public function fetch(FetchRequest $request): FetchResult;

    /**
     * Check if domain is currently rate limited.
     */
    public function isRateLimited(string $domain): bool;
}
