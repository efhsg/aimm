<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\AddFocalRequest;
use app\dto\peergroup\MemberActionResult;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Throwable;
use yii\log\Logger;

/**
 * Handler for adding a focal designation to a peer group member.
 */
final class AddFocalHandler implements AddFocalInterface
{
    public function __construct(
        private readonly PeerGroupQuery $groupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function addFocal(AddFocalRequest $request): MemberActionResult
    {
        $this->logger->log(
            [
                'message' => 'Adding focal to peer group',
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
            $this->memberQuery->addFocal($request->groupId, $request->companyId);

            $this->logger->log(
                [
                    'message' => 'Added focal to peer group',
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
                    'message' => 'Failed to add focal to peer group',
                    'group_id' => $request->groupId,
                    'company_id' => $request->companyId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'peergroup'
            );

            return MemberActionResult::failure(['Failed to add focal: ' . $e->getMessage()]);
        }
    }
}
