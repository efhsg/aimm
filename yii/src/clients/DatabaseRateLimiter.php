<?php

declare(strict_types=1);

namespace app\clients;

use app\queries\SourceBlockRepositoryInterface;
use DateTimeImmutable;

/**
 * Database-backed rate limiter for production/multi-process use.
 *
 * Uses SourceBlockRepository for persistence, ensuring proper concurrency
 * handling via database transactions.
 */
final class DatabaseRateLimiter implements RateLimiterInterface
{
    /**
     * Minimum delay between requests per domain (in milliseconds).
     */
    private const DOMAIN_DELAYS_MS = [
        'finance.yahoo.com' => 2000,
        'query1.finance.yahoo.com' => 2500,
        'www.reuters.com' => 3000,
        'default' => 1000,
    ];

    /** @var array<string, float> In-memory last request time (per-process) */
    private array $lastRequestTime = [];

    public function __construct(
        private readonly SourceBlockRepositoryInterface $repository,
    ) {
    }

    public function isRateLimited(string $domain): bool
    {
        return $this->repository->isBlocked($domain);
    }

    public function getRetryTime(string $domain): ?DateTimeImmutable
    {
        return $this->repository->getBlockedUntil($domain);
    }

    public function wait(string $domain): void
    {
        $delayMs = self::DOMAIN_DELAYS_MS[$domain] ?? self::DOMAIN_DELAYS_MS['default'];
        $lastTime = $this->lastRequestTime[$domain] ?? 0;
        $now = microtime(true) * 1000;
        $elapsed = $now - $lastTime;

        if ($elapsed < $delayMs) {
            usleep((int) (($delayMs - $elapsed) * 1000));
        }
    }

    public function recordAttempt(string $domain): void
    {
        $this->lastRequestTime[$domain] = microtime(true) * 1000;
    }

    public function recordSuccess(string $domain): void
    {
        $this->repository->clearBlock($domain);
    }

    public function recordBlock(string $domain, DateTimeImmutable $retryUntil): void
    {
        $this->repository->recordBlock($domain, $retryUntil);
    }

    public function getConsecutiveBlockCount(string $domain): int
    {
        return $this->repository->getConsecutiveCount($domain);
    }

    public function block(string $domain, DateTimeImmutable $until): void
    {
        $this->repository->recordBlock($domain, $until);
    }
}
