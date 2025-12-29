<?php

declare(strict_types=1);

namespace tests\unit\clients;

use app\clients\DatabaseRateLimiter;
use app\queries\SourceBlockRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\clients\DatabaseRateLimiter
 */
final class DatabaseRateLimiterTest extends Unit
{
    public function testIsRateLimitedReturnsTrueWhenBlocked(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('isBlocked')
            ->with('example.com')
            ->willReturn(true);

        $limiter = new DatabaseRateLimiter($repository);
        $result = $limiter->isRateLimited('example.com');

        $this->assertTrue($result);
    }

    public function testIsRateLimitedReturnsFalseWhenNotBlocked(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('isBlocked')
            ->with('example.com')
            ->willReturn(false);

        $limiter = new DatabaseRateLimiter($repository);
        $result = $limiter->isRateLimited('example.com');

        $this->assertFalse($result);
    }

    public function testGetRetryTimeReturnsDateTimeWhenBlocked(): void
    {
        $retryTime = new DateTimeImmutable('2025-01-15 12:00:00');

        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getBlockedUntil')
            ->with('example.com')
            ->willReturn($retryTime);

        $limiter = new DatabaseRateLimiter($repository);
        $result = $limiter->getRetryTime('example.com');

        $this->assertSame($retryTime, $result);
    }

    public function testGetRetryTimeReturnsNullWhenNotBlocked(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getBlockedUntil')
            ->with('example.com')
            ->willReturn(null);

        $limiter = new DatabaseRateLimiter($repository);
        $result = $limiter->getRetryTime('example.com');

        $this->assertNull($result);
    }

    public function testRecordAttemptTracksRequestTime(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);

        $limiter = new DatabaseRateLimiter($repository);
        $limiter->recordAttempt('example.com');

        // Verify that wait() respects the recorded time by not sleeping on immediate call
        $start = microtime(true);
        $limiter->recordAttempt('example.com');
        $limiter->wait('example.com');
        $elapsed = (microtime(true) - $start) * 1000;

        // Should have waited at least some time (default delay is 1000ms)
        $this->assertGreaterThanOrEqual(900, $elapsed);
    }

    public function testRecordSuccessClearsBlock(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('clearBlock')
            ->with('example.com');

        $limiter = new DatabaseRateLimiter($repository);
        $limiter->recordSuccess('example.com');
    }

    public function testRecordBlockDelegatesToRepository(): void
    {
        $retryUntil = new DateTimeImmutable('2025-01-15 12:00:00');

        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('recordBlock')
            ->with('example.com', $retryUntil);

        $limiter = new DatabaseRateLimiter($repository);
        $limiter->recordBlock('example.com', $retryUntil);
    }

    public function testGetConsecutiveBlockCountDelegatesToRepository(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getConsecutiveCount')
            ->with('example.com')
            ->willReturn(5);

        $limiter = new DatabaseRateLimiter($repository);
        $result = $limiter->getConsecutiveBlockCount('example.com');

        $this->assertSame(5, $result);
    }

    public function testBlockDelegatesToRepository(): void
    {
        $until = new DateTimeImmutable('2025-01-15 12:00:00');

        $repository = $this->createMock(SourceBlockRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('recordBlock')
            ->with('example.com', $until);

        $limiter = new DatabaseRateLimiter($repository);
        $limiter->block('example.com', $until);
    }

    public function testWaitUsesDefaultDelayForUnknownDomain(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);

        $limiter = new DatabaseRateLimiter($repository);
        $limiter->recordAttempt('unknown.com');

        $start = microtime(true);
        $limiter->wait('unknown.com');
        $elapsed = (microtime(true) - $start) * 1000;

        // Default delay is 1000ms
        $this->assertGreaterThanOrEqual(900, $elapsed);
        $this->assertLessThan(1200, $elapsed);
    }

    public function testWaitUsesKnownDomainDelay(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);

        $limiter = new DatabaseRateLimiter($repository);
        $limiter->recordAttempt('finance.yahoo.com');

        $start = microtime(true);
        $limiter->wait('finance.yahoo.com');
        $elapsed = (microtime(true) - $start) * 1000;

        // Yahoo delay is 2000ms
        $this->assertGreaterThanOrEqual(1900, $elapsed);
        $this->assertLessThan(2200, $elapsed);
    }

    public function testWaitDoesNotSleepWhenEnoughTimeHasPassed(): void
    {
        $repository = $this->createMock(SourceBlockRepositoryInterface::class);

        $limiter = new DatabaseRateLimiter($repository);
        // Don't record attempt - no last request time

        $start = microtime(true);
        $limiter->wait('example.com');
        $elapsed = (microtime(true) - $start) * 1000;

        // Should not have waited at all (< 100ms)
        $this->assertLessThan(100, $elapsed);
    }
}
