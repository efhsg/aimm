<?php

declare(strict_types=1);

namespace tests\unit\commands;

use app\commands\CompressValuationController;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use yii\base\Module;
use yii\console\ExitCode;
use yii\db\Command;
use yii\db\Connection;
use yii\db\Transaction;
use yii\log\Logger;

final class CompressValuationControllerTest extends Unit
{
    private Module&MockObject $module;
    private Connection&MockObject $db;
    private Logger&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->createMock(Module::class);
        $this->db = $this->createMock(Connection::class);
        $this->logger = $this->createMock(Logger::class);
    }

    public function testActionIndexReturnsOkOnSuccess(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('commit');

        $this->db->method('beginTransaction')->willReturn($transaction);

        $command = $this->createMock(Command::class);
        $command->method('execute')->willReturn(0);
        $this->db->method('createCommand')->willReturn($command);

        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );

        $exitCode = $controller->actionIndex();

        $this->assertSame(ExitCode::OK, $exitCode);
    }

    public function testActionIndexRollsBackOnDryRun(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('rollBack');
        $transaction->expects($this->never())->method('commit');

        $this->db->method('beginTransaction')->willReturn($transaction);

        $command = $this->createMock(Command::class);
        $command->method('execute')->willReturn(5);
        $this->db->method('createCommand')->willReturn($command);

        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );
        $controller->dryRun = true;

        $exitCode = $controller->actionIndex();

        $this->assertSame(ExitCode::OK, $exitCode);
    }

    public function testActionIndexReturnsErrorOnException(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('rollBack');

        $this->db->method('beginTransaction')->willReturn($transaction);
        $this->db->method('createCommand')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                $this->callback(static fn (array $data): bool => $data['message'] === 'Valuation compression failed'),
                Logger::LEVEL_ERROR,
                'valuation-compression'
            );

        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );

        $exitCode = $controller->actionIndex();

        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
    }

    public function testActionIndexExecutesQueriesInCorrectOrder(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('commit');
        $this->db->method('beginTransaction')->willReturn($transaction);

        $executionOrder = [];

        $command = $this->createMock(Command::class);
        $command->method('execute')->willReturnCallback(function () use (&$executionOrder) {
            $executionOrder[] = 'execute';
            return 1;
        });

        $this->db->expects($this->exactly(4))
            ->method('createCommand')
            ->willReturnCallback(function (string $sql) use ($command, &$executionOrder) {
                // Match promote weekly: UPDATE with SET 'weekly' and WHERE 'daily'
                if (str_contains($sql, 'UPDATE') && str_contains($sql, "SET retention_tier = 'weekly'")) {
                    $executionOrder[] = 'promote_weekly';
                    // Match delete daily: DELETE with 'daily' tier
                } elseif (str_contains($sql, 'DELETE') && str_contains($sql, "retention_tier = 'daily'")) {
                    $executionOrder[] = 'delete_daily';
                    // Match promote monthly: UPDATE with SET 'monthly'
                } elseif (str_contains($sql, 'UPDATE') && str_contains($sql, "SET vs.retention_tier = 'monthly'")) {
                    $executionOrder[] = 'promote_monthly';
                    // Match delete weekly: DELETE with 'weekly' tier
                } elseif (str_contains($sql, 'DELETE') && str_contains($sql, "retention_tier = 'weekly'")) {
                    $executionOrder[] = 'delete_weekly';
                }
                return $command;
            });

        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );

        $controller->actionIndex();

        $this->assertSame([
            'promote_weekly',
            'execute',
            'delete_daily',
            'execute',
            'promote_monthly',
            'execute',
            'delete_weekly',
            'execute',
        ], $executionOrder);
    }

    public function testOptionsIncludesDryRun(): void
    {
        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );

        $options = $controller->options('index');

        $this->assertContains('dryRun', $options);
    }

    public function testOptionAliasesIncludesDForDryRun(): void
    {
        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );

        $aliases = $controller->optionAliases();

        $this->assertArrayHasKey('d', $aliases);
        $this->assertSame('dryRun', $aliases['d']);
    }

    public function testLogsSuccessWithCounts(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('commit');
        $this->db->method('beginTransaction')->willReturn($transaction);

        $command = $this->createMock(Command::class);
        $command->method('execute')
            ->willReturnOnConsecutiveCalls(10, 50, 5, 20);
        $this->db->method('createCommand')->willReturn($command);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                $this->callback(static fn (array $data): bool => $data['message'] === 'Valuation compression completed'
                        && $data['weekly_promoted'] === 10
                        && $data['weekly_deleted'] === 50
                        && $data['monthly_promoted'] === 5
                        && $data['monthly_deleted'] === 20
                        && $data['dry_run'] === false),
                Logger::LEVEL_INFO,
                'valuation-compression'
            );

        $controller = new CompressValuationController(
            'compress-valuation',
            $this->module,
            $this->db,
            $this->logger
        );

        $controller->actionIndex();
    }
}
