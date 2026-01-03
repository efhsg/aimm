<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\CreatePeerGroupRequest;
use app\dto\peergroup\PeerGroupResponse;
use app\dto\peergroup\SavePeerGroupResult;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for creating new peer groups.
 */
final class CreatePeerGroupHandler implements CreatePeerGroupInterface
{
    public function __construct(
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function create(CreatePeerGroupRequest $request): SavePeerGroupResult
    {
        $this->logger->log(
            [
                'message' => 'Creating peer group',
                'slug' => $request->slug,
                'sector' => $request->sector,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $errors = $this->validate($request);
        if (!empty($errors)) {
            $this->logger->log(
                [
                    'message' => 'Validation failed for peer group',
                    'slug' => $request->slug,
                    'error_count' => count($errors),
                ],
                Logger::LEVEL_WARNING,
                'peergroup'
            );
            return SavePeerGroupResult::failure($errors);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $groupId = $this->peerGroupQuery->insert([
                'slug' => $request->slug,
                'name' => $request->name,
                'sector' => $request->sector,
                'description' => $request->description,
                'policy_id' => $request->policyId,
                'is_active' => $request->isActive ? 1 : 0,
                'created_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Peer group created successfully',
                    'slug' => $request->slug,
                    'id' => $groupId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'peergroup'
            );

            $group = $this->peerGroupQuery->findById($groupId);

            return SavePeerGroupResult::success($this->toResponse($group));
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to create peer group',
                    'slug' => $request->slug,
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
    private function validate(CreatePeerGroupRequest $request): array
    {
        $errors = [];

        if (empty($request->name)) {
            $errors[] = 'Name is required.';
        }

        if (empty($request->slug)) {
            $errors[] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $request->slug)) {
            $errors[] = 'Slug must contain only lowercase letters, numbers, and hyphens.';
        } elseif ($this->peerGroupQuery->findBySlug($request->slug) !== null) {
            $errors[] = 'A peer group with this slug already exists.';
        }

        if (empty($request->sector)) {
            $errors[] = 'Sector is required.';
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
