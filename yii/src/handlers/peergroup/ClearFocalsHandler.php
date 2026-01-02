<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\ClearFocalsRequest;
use app\dto\peergroup\MemberActionResult;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Throwable;
use yii\log\Logger;

/**
 * Handler for clearing all focal designations from a peer group.
 */
final class ClearFocalsHandler implements ClearFocalsInterface
{
    public function __construct(
        private readonly PeerGroupQuery $groupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function clearFocals(ClearFocalsRequest $request): MemberActionResult
    {
        $this->logger->log(
            [
                'message' => 'Clearing all focals from peer group',
                'group_id' => $request->groupId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $group = $this->groupQuery->findById($request->groupId);
        if ($group === null) {
            return MemberActionResult::failure(['Peer group not found.']);
        }

        try {
            $this->memberQuery->clearFocals($request->groupId);

            $this->logger->log(
                [
                    'message' => 'Cleared all focals from peer group',
                    'group_id' => $request->groupId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'peergroup'
            );

            return MemberActionResult::success();
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Failed to clear focals from peer group',
                    'group_id' => $request->groupId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'peergroup'
            );

            return MemberActionResult::failure(['Failed to clear focals: ' . $e->getMessage()]);
        }
    }
}
