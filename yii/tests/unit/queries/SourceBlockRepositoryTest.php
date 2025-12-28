<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\SourceBlockRepository;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Command;
use yii\db\Connection;

/**
 * @covers \app\queries\SourceBlockRepository
 */
final class SourceBlockRepositoryTest extends Unit
{
    public function testIsBlockedReturnsTrueWhenBlockActive(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(['id' => 1]);

        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('createCommand')
            ->with($this->stringContains('blocked_until > :now'))
            ->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->isBlocked('example.com');

        $this->assertTrue($result);
    }

    public function testIsBlockedReturnsFalseWhenNotBlocked(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->isBlocked('example.com');

        $this->assertFalse($result);
    }

    public function testGetBlockedUntilReturnsDateTimeWhenExists(): void
    {
        $blockedUntil = '2025-01-15 12:00:00';

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(['blocked_until' => $blockedUntil]);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->getBlockedUntil('example.com');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame($blockedUntil, $result->format('Y-m-d H:i:s'));
    }

    public function testGetBlockedUntilReturnsNullWhenNotExists(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->getBlockedUntil('example.com');

        $this->assertNull($result);
    }

    public function testRecordBlockInsertsNewBlock(): void
    {
        $blockedUntil = new DateTimeImmutable('2025-01-15 12:00:00');

        $selectCommand = $this->createMock(Command::class);
        $selectCommand->method('bindValue')->willReturnSelf();
        $selectCommand->method('queryOne')->willReturn(false);

        $insertCommand = $this->createMock(Command::class);
        $insertCommand->expects($this->once())
            ->method('insert')
            ->with(
                '{{%source_block}}',
                $this->callback(function (array $data) use ($blockedUntil): bool {
                    return $data['domain'] === 'example.com'
                        && isset($data['blocked_at'])
                        && $data['blocked_until'] === $blockedUntil->format('Y-m-d H:i:s')
                        && $data['consecutive_count'] === 1
                        && $data['last_status_code'] === 403
                        && $data['last_error'] === 'Forbidden';
                }),
            )
            ->willReturnSelf();
        $insertCommand->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->expects($this->exactly(2))
            ->method('createCommand')
            ->willReturnOnConsecutiveCalls($selectCommand, $insertCommand);

        $repository = new SourceBlockRepository($db);
        $repository->recordBlock('example.com', $blockedUntil, 403, 'Forbidden');
    }

    public function testRecordBlockUpdatesExistingBlock(): void
    {
        $blockedUntil = new DateTimeImmutable('2025-01-15 12:00:00');

        $selectCommand = $this->createMock(Command::class);
        $selectCommand->method('bindValue')->willReturnSelf();
        $selectCommand->method('queryOne')->willReturn([
            'id' => 42,
            'consecutive_count' => 2,
        ]);

        $updateCommand = $this->createMock(Command::class);
        $updateCommand->expects($this->once())
            ->method('update')
            ->with(
                '{{%source_block}}',
                $this->callback(function (array $data) use ($blockedUntil): bool {
                    return isset($data['blocked_at'])
                        && $data['blocked_until'] === $blockedUntil->format('Y-m-d H:i:s')
                        && $data['consecutive_count'] === 3
                        && $data['last_status_code'] === 429
                        && $data['last_error'] === 'Rate limited';
                }),
                ['id' => 42],
            )
            ->willReturnSelf();
        $updateCommand->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->expects($this->exactly(2))
            ->method('createCommand')
            ->willReturnOnConsecutiveCalls($selectCommand, $updateCommand);

        $repository = new SourceBlockRepository($db);
        $repository->recordBlock('example.com', $blockedUntil, 429, 'Rate limited');
    }

    public function testRecordBlockWithNullStatusAndError(): void
    {
        $blockedUntil = new DateTimeImmutable('2025-01-15 12:00:00');

        $selectCommand = $this->createMock(Command::class);
        $selectCommand->method('bindValue')->willReturnSelf();
        $selectCommand->method('queryOne')->willReturn(false);

        $insertCommand = $this->createMock(Command::class);
        $insertCommand->expects($this->once())
            ->method('insert')
            ->with(
                '{{%source_block}}',
                $this->callback(function (array $data): bool {
                    return $data['last_status_code'] === null
                        && $data['last_error'] === null;
                }),
            )
            ->willReturnSelf();
        $insertCommand->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')
            ->willReturnOnConsecutiveCalls($selectCommand, $insertCommand);

        $repository = new SourceBlockRepository($db);
        $repository->recordBlock('example.com', $blockedUntil);
    }

    public function testGetConsecutiveCountReturnsCount(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(['consecutive_count' => 5]);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->getConsecutiveCount('example.com');

        $this->assertSame(5, $result);
    }

    public function testGetConsecutiveCountReturnsZeroWhenNotExists(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->getConsecutiveCount('example.com');

        $this->assertSame(0, $result);
    }

    public function testClearBlockResetsConsecutiveCount(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with(
                '{{%source_block}}',
                ['consecutive_count' => 0],
                ['domain' => 'example.com'],
            )
            ->willReturnSelf();
        $command->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $repository->clearBlock('example.com');

        $this->assertTrue(true);
    }

    public function testCleanupExpiredDeletesExpiredWithZeroCount(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('delete')
            ->with(
                '{{%source_block}}',
                'blocked_until < :now AND consecutive_count = 0',
                $this->callback(function (array $params): bool {
                    return isset($params[':now']);
                }),
            )
            ->willReturnSelf();
        $command->expects($this->once())
            ->method('execute')
            ->willReturn(3);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->cleanupExpired();

        $this->assertSame(3, $result);
    }

    public function testCleanupExpiredReturnsZeroWhenNothingToDelete(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('delete')->willReturnSelf();
        $command->method('execute')->willReturn(0);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $repository = new SourceBlockRepository($db);
        $result = $repository->cleanupExpired();

        $this->assertSame(0, $result);
    }
}
