<?php

declare(strict_types=1);

namespace app\clients;

use DateTimeImmutable;

/**
 * Manages request pacing and block tracking per domain.
 */
interface RateLimiterInterface
{
    /**
     * Check if domain is currently blocked.
     */
    public function isRateLimited(string $domain): bool;

    /**
     * Get the time when the domain will be unblocked.
     */
    public function getRetryTime(string $domain): ?DateTimeImmutable;

    /**
     * Wait for rate limit window (implements per-domain pacing).
     */
    public function wait(string $domain): void;

    /**
     * Record that an HTTP request was attempted (does not reset block counts).
     */
    public function recordAttempt(string $domain): void;

    /**
     * Record a successful request (resets consecutive block count).
     *
     * Success means: request completed AND did not result in a hard block (401/403)
     * and did not trigger a soft-block detector (CAPTCHA / JS challenge / rate-limit page).
     */
    public function recordSuccess(string $domain): void;

    /**
     * Record a block (401/403) with exponential backoff tracking.
     */
    public function recordBlock(string $domain, DateTimeImmutable $retryUntil): void;

    /**
     * Get the number of consecutive blocks for a domain.
     */
    public function getConsecutiveBlockCount(string $domain): int;

    /**
     * Block a domain until specified time (for 429 responses).
     */
    public function block(string $domain, DateTimeImmutable $until): void;
}
