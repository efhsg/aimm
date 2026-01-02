<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\MemberActionResult;
use app\dto\peergroup\RemoveFocalRequest;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Throwable;
use yii\log\Logger;

/**
 * Handler for removing a focal designation from a peer group member.
 */
final class RemoveFocalHandler implements RemoveFocalInterface
{
    public function __construct(
        private readonly PeerGroupQuery $groupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function removeFocal(RemoveFocalRequest $request): MemberActionResult
    {
        $this->logger->log(
            [
                'message' => 'Removing focal from peer group',
                'group_id' => $request->groupId,
                'company_id' => $request->companyId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $group = $this->groupQuery->findById($request->groupId);
        if ($group === null) {
            return MemberActionResult::failure(['Peer group not found.']);
        }

        if (!$this->memberQuery->isMember($request->groupId, $request->companyId)) {
            return MemberActionResult::failure(['Company is not a member of this group.']);
        }

        try {
            $this->memberQuery->removeFocal($request->groupId, $request->companyId);

            $this->logger->log(
                [
                    'message' => 'Removed focal from peer group',
                    'group_id' => $request->groupId,
                    'company_id' => $request->companyId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'peergroup'
            );

            return MemberActionResult::success();
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Failed to remove focal from peer group',
                    'group_id' => $request->groupId,
                    'company_id' => $request->companyId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'peergroup'
            );

            return MemberActionResult::failure(['Failed to remove focal: ' . $e->getMessage()]);
        }
    }
}
