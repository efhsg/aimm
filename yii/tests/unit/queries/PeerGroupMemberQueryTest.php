<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\PeerGroupMemberQuery;
use Codeception\Test\Unit;
use yii\db\Command;
use yii\db\Connection;

final class PeerGroupMemberQueryTest extends Unit
{
    public function testFindByGroupReturnsMembersWithCompanyData(): void
    {
        $expected = [
            ['company_id' => 1, 'is_focal' => 1, 'display_order' => 0, 'ticker' => 'SHEL', 'name' => 'Shell plc'],
            ['company_id' => 2, 'is_focal' => 0, 'display_order' => 1, 'ticker' => 'XOM', 'name' => 'Exxon Mobil'],
        ];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryAll')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertEquals($expected, $query->findByGroup(1));
    }

    public function testFindFocalByGroupReturnsFocalCompany(): void
    {
        $expected = ['company_id' => 1, 'ticker' => 'SHEL', 'name' => 'Shell plc'];

        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn($expected);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertEquals($expected, $query->findFocalByGroup(1));
    }

    public function testFindFocalByGroupReturnsNullWhenNoFocal(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryOne')->willReturn(false);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertNull($query->findFocalByGroup(1));
    }

    public function testIsMemberReturnsTrueWhenMember(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryScalar')->willReturn('1');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertTrue($query->isMember(1, 10));
    }

    public function testIsMemberReturnsFalseWhenNotMember(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValues')->willReturnSelf();
        $command->method('queryScalar')->willReturn('0');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertFalse($query->isMember(1, 999));
    }

    public function testAddMemberInsertsRow(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('insert')
            ->with('industry_peer_group_member', [
                'peer_group_id' => 1,
                'company_id' => 10,
                'is_focal' => 1,
                'display_order' => 0,
                'added_by' => 'admin',
            ])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $query->addMember(1, 10, true, 0, 'admin');
    }

    public function testRemoveMemberDeletesRow(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('delete')
            ->with('industry_peer_group_member', [
                'peer_group_id' => 1,
                'company_id' => 10,
            ])
            ->willReturnSelf();
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $query->removeMember(1, 10);
    }

    public function testSetFocalClearsExistingAndSetsNew(): void
    {
        $clearCommand = $this->createMock(Command::class);
        $clearCommand->expects($this->once())
            ->method('update')
            ->with(
                'industry_peer_group_member',
                ['is_focal' => 0],
                ['peer_group_id' => 1, 'is_focal' => 1]
            )
            ->willReturnSelf();
        $clearCommand->method('execute');

        $setCommand = $this->createMock(Command::class);
        $setCommand->expects($this->once())
            ->method('update')
            ->with(
                'industry_peer_group_member',
                ['is_focal' => 1],
                ['peer_group_id' => 1, 'company_id' => 10]
            )
            ->willReturnSelf();
        $setCommand->method('execute');

        $db = $this->createMock(Connection::class);
        $db->expects($this->exactly(2))
            ->method('createCommand')
            ->willReturnOnConsecutiveCalls($clearCommand, $setCommand);

        $query = new PeerGroupMemberQuery($db);
        $query->setFocal(1, 10);
    }

    public function testCountByGroupReturnsCount(): void
    {
        $command = $this->createMock(Command::class);
        $command->method('bindValue')->willReturnSelf();
        $command->method('queryScalar')->willReturn('5');

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertEquals(5, $query->countByGroup(1));
    }

    public function testRemoveAllFromGroupDeletesAllMembers(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('delete')
            ->with('industry_peer_group_member', ['peer_group_id' => 1])
            ->willReturnSelf();
        $command->method('execute')->willReturn(5);

        $db = $this->createMock(Connection::class);
        $db->method('createCommand')->willReturn($command);

        $query = new PeerGroupMemberQuery($db);
        $this->assertEquals(5, $query->removeAllFromGroup(1));
    }
}
