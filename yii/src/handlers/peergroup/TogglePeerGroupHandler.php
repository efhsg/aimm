<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\PeerGroupResponse;
use app\dto\peergroup\SavePeerGroupResult;
use app\dto\peergroup\TogglePeerGroupRequest;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for toggling peer group active status.
 */
final class TogglePeerGroupHandler implements TogglePeerGroupInterface
{
    public function __construct(
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function toggle(TogglePeerGroupRequest $request): SavePeerGroupResult
    {
        $this->logger->log(
            [
                'message' => 'Toggling peer group status',
                'id' => $request->id,
                'is_active' => $request->isActive,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $existing = $this->peerGroupQuery->findById($request->id);
        if ($existing === null) {
            return SavePeerGroupResult::failure(['Peer group not found.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if ($request->isActive) {
                $this->peerGroupQuery->activate($request->id);
            } else {
                $this->peerGroupQuery->deactivate($request->id);
            }

            $this->peerGroupQuery->update($request->id, [
                'updated_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $action = $request->isActive ? 'activated' : 'deactivated';
            $this->logger->log(
                [
                    'message' => "Peer group {$action} successfully",
                    'id' => $request->id,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'peergroup'
            );

            $group = $this->peerGroupQuery->findById($request->id);

            return SavePeerGroupResult::success($this->toResponse($group));
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to toggle peer group status',
                    'id' => $request->id,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'peergroup'
            );

            return SavePeerGroupResult::failure(['Failed to save: ' . $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toResponse(array $row): PeerGroupResponse
    {
        $groupId = (int) $row['id'];
        $memberCount = $this->memberQuery->countByGroup($groupId);
        $focals = $this->memberQuery->findFocalsByGroup($groupId);
        $focalTickers = array_column($focals, 'ticker');

        return new PeerGroupResponse(
            id: $groupId,
            slug: $row['slug'],
            name: $row['name'],
            sector: $row['sector'],
            description: $row['description'] ?? null,
            policyId: $row['policy_id'] !== null ? (int) $row['policy_id'] : null,
            policyName: null,
            isActive: (bool) $row['is_active'],
            memberCount: $memberCount,
            focalCount: count($focalTickers),
            focalTickers: $focalTickers,
            lastRunStatus: null,
            lastRunAt: null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] ?? null,
            updatedBy: $row['updated_by'] ?? null,
        );
    }
}
