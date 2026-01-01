<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\CollectionPolicyQuery;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

final class CollectionPolicyQueryTest extends Unit
{
    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $expected = ['id' => 1, 'slug' => 'test-policy'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $this->assertEquals($expected, $query->findById(1));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $this->assertNull($query->findById(999));
    }

    public function testFindBySlugReturnsArrayWhenFound(): void
    {
        $expected = ['id' => 1, 'slug' => 'oil-gas-standard'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $this->assertEquals($expected, $query->findBySlug('oil-gas-standard'));
    }

    public function testFindDefaultForSectorReturnsArrayWhenFound(): void
    {
        $expected = ['id' => 1, 'slug' => 'energy-default', 'is_default_for_sector' => 'Energy'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $this->assertEquals($expected, $query->findDefaultForSector('Energy'));
    }

    public function testFindDefaultForSectorReturnsNullWhenNoDefault(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $this->assertNull($query->findDefaultForSector('Unknown'));
    }

    public function testFindAllReturnsList(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'Policy A'],
            ['id' => 2, 'name' => 'Policy B'],
        ];

        $command = $this->createMock(Command::class);
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $this->assertEquals($expected, $query->findAll());
    }

    public function testInsertReturnsId(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('insert')->willReturnSelf();
        $command->method('execute')->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);
        $db->method('getLastInsertID')->willReturn('42');

        $query = new CollectionPolicyQuery($db);
        $this->assertEquals(42, $query->insert(['slug' => 'new-policy']));
    }

    public function testUpdateExecutesCommand(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('update')
            ->with('collection_policy', ['name' => 'Updated'], ['id' => 1])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $query->update(1, ['name' => 'Updated']);
    }

    public function testDeleteExecutesCommand(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('delete')
            ->with('collection_policy', ['id' => 1])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new CollectionPolicyQuery($db);
        $query->delete(1);
    }
}
