<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\PeerGroupResponse;
use app\dto\peergroup\SavePeerGroupResult;
use app\dto\peergroup\UpdatePeerGroupRequest;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for updating existing peer groups.
 */
final class UpdatePeerGroupHandler implements UpdatePeerGroupInterface
{
    public function __construct(
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function update(UpdatePeerGroupRequest $request): SavePeerGroupResult
    {
        $this->logger->log(
            [
                'message' => 'Updating peer group',
                'id' => $request->id,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $existing = $this->peerGroupQuery->findById($request->id);
        if ($existing === null) {
            return SavePeerGroupResult::failure(['Peer group not found.']);
        }

        $errors = $this->validate($request);
        if (!empty($errors)) {
            $this->logger->log(
                [
                    'message' => 'Validation failed for peer group update',
                    'id' => $request->id,
                    'error_count' => count($errors),
                ],
                Logger::LEVEL_WARNING,
                'peergroup'
            );
            return SavePeerGroupResult::failure($errors);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->peerGroupQuery->update($request->id, [
                'name' => $request->name,
                'description' => $request->description,
                'policy_id' => $request->policyId,
                'updated_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Peer group updated successfully',
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
                    'message' => 'Failed to update peer group',
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
     * @return string[]
     */
    private function validate(UpdatePeerGroupRequest $request): array
    {
        $errors = [];

        if (empty($request->name)) {
            $errors[] = 'Name is required.';
        }

        return $errors;
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
