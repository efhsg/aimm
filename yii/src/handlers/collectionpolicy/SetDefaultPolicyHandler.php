<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\SetDefaultPolicyRequest;
use app\queries\CollectionPolicyQuery;
use app\queries\SectorQuery;
use Throwable;
use Yii;
use yii\db\Connection;
use yii\log\Logger;

/**
 * Handler for setting or clearing a sector default policy.
 *
 * Sets the policy on all industries in the given sector.
 */
final class SetDefaultPolicyHandler implements SetDefaultPolicyInterface
{
    public function __construct(
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly SectorQuery $sectorQuery,
        private readonly Connection $db,
        private readonly Logger $logger,
    ) {
    }

    public function setDefault(SetDefaultPolicyRequest $request): CollectionPolicyResult
    {
        $this->logger->log(
            [
                'message' => $request->clear ? 'Clearing sector default policy' : 'Setting sector default policy',
                'policy_id' => $request->id,
                'sector' => $request->sector,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collectionpolicy'
        );

        $policy = $this->policyQuery->findById($request->id);
        if ($policy === null) {
            return CollectionPolicyResult::failure(['Policy not found.']);
        }

        $sector = $this->sectorQuery->findBySlug($request->sector);
        if ($sector === null) {
            return CollectionPolicyResult::failure(['Sector not found: ' . $request->sector]);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $policyId = $request->clear ? null : $request->id;

            // Update all industries in this sector
            $affectedCount = $this->db->createCommand()
                ->update(
                    '{{%industry}}',
                    ['policy_id' => $policyId],
                    ['sector_id' => $sector['id']]
                )
                ->execute();

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => $request->clear ? 'Cleared sector default policy' : 'Set sector default policy',
                    'policy_id' => $request->id,
                    'sector' => $request->sector,
                    'affected_industries' => $affectedCount,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'collectionpolicy'
            );

            return CollectionPolicyResult::success($policy);
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to set sector default policy',
                    'policy_id' => $request->id,
                    'sector' => $request->sector,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collectionpolicy'
            );

            return CollectionPolicyResult::failure(['Failed to set default policy: ' . $e->getMessage()]);
        }
    }
}
