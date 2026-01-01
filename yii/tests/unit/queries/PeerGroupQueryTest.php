<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\PeerGroupQuery;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

final class PeerGroupQueryTest extends Unit
{
    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $expected = ['id' => 1, 'slug' => 'test-group'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $this->assertEquals($expected, $query->findById(1));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $this->assertNull($query->findById(999));
    }

    public function testFindBySlugReturnsArrayWhenFound(): void
    {
        $expected = ['id' => 1, 'slug' => 'global-energy-supermajors'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $this->assertEquals($expected, $query->findBySlug('global-energy-supermajors'));
    }

    public function testFindAllActiveReturnsList(): void
    {
        $expected = [
            ['id' => 1, 'slug' => 'group-a', 'is_active' => true],
            ['id' => 2, 'slug' => 'group-b', 'is_active' => true],
        ];

        $command = $this->createMock(Command::class);
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $this->assertEquals($expected, $query->findAllActive());
    }

    public function testFindBySectorReturnsList(): void
    {
        $expected = [['id' => 1, 'slug' => 'energy-group', 'sector' => 'Energy']];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $this->assertEquals($expected, $query->findBySector('Energy'));
    }

    public function testFindByCompanyIdReturnsList(): void
    {
        $expected = [
            ['id' => 1, 'slug' => 'oil-majors'],
            ['id' => 2, 'slug' => 'dividend-aristocrats'],
        ];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $this->assertEquals($expected, $query->findByCompanyId(123));
    }

    public function testInsertReturnsId(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('insert')->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('42');

        $query = new PeerGroupQuery($db);
        $this->assertEquals(42, $query->insert(['slug' => 'new-group']));
    }

    public function testDeactivateSetsIsActiveFalse(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with('industry_peer_group', ['is_active' => false], ['id' => 1])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $query->deactivate(1);
    }

    public function testActivateSetsIsActiveTrue(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with('industry_peer_group', ['is_active' => true], ['id' => 1])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $query->activate(1);
    }

    public function testAssignPolicyUpdatesPolicyId(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with('industry_peer_group', ['policy_id' => 5], ['id' => 1])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $query->assignPolicy(1, 5);
    }

    public function testAssignPolicyWithNullClearsPolicy(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with('industry_peer_group', ['policy_id' => null], ['id' => 1])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupQuery($db);
        $query->assignPolicy(1, null);
    }
}
