<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\MemberActionResult;
use app\dto\peergroup\RemoveMemberRequest;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for removing a member from a peer group.
 */
final class RemoveMemberHandler implements RemoveMemberInterface
{
    public function __construct(
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function remove(RemoveMemberRequest $request): MemberActionResult
    {
        $this->logger->log(
            [
                'message' => 'Removing member from peer group',
                'group_id' => $request->groupId,
                'company_id' => $request->companyId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $group = $this->peerGroupQuery->findById($request->groupId);
        if ($group === null) {
            return MemberActionResult::failure(['Peer group not found.']);
        }

        if (!$this->memberQuery->isMember($request->groupId, $request->companyId)) {
            return MemberActionResult::failure(['Company is not a member of this group.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->memberQuery->removeMember($request->groupId, $request->companyId);

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Member removed from peer group',
                    'group_id' => $request->groupId,
                    'company_id' => $request->companyId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'peergroup'
            );

            return MemberActionResult::success();
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to remove member from peer group',
                    'group_id' => $request->groupId,
                    'company_id' => $request->companyId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'peergroup'
            );

            return MemberActionResult::failure(['Failed to remove member: ' . $e->getMessage()]);
        }
    }
}
