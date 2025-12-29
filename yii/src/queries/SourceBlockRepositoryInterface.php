<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;

/**
 * Interface for source block persistence.
 */
interface SourceBlockRepositoryInterface
{
    /**
     * Check if a domain is currently blocked.
     */
    public function isBlocked(string $domain): bool;

    /**
     * Get the time when the domain will be unblocked.
     */
    public function getBlockedUntil(string $domain): ?DateTimeImmutable;

    /**
     * Record a block with optional status code and error message.
     */
    public function recordBlock(
        string $domain,
        DateTimeImmutable $blockedUntil,
        ?int $statusCode = null,
        ?string $error = null,
    ): void;

    /**
     * Get the number of consecutive blocks for a domain.
     */
    public function getConsecutiveCount(string $domain): int;

    /**
     * Clear block status for a domain (resets consecutive count).
     */
    public function clearBlock(string $domain): void;

    /**
     * Delete expired blocks with zero consecutive count.
     *
     * @return int Number of deleted rows
     */
    public function cleanupExpired(): int;
}
