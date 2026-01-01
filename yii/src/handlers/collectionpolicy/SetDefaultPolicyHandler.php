<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\SetDefaultPolicyRequest;
use app\queries\CollectionPolicyQuery;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for setting or clearing sector default policies.
 */
final class SetDefaultPolicyHandler implements SetDefaultPolicyInterface
{
    public function __construct(
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function setDefault(SetDefaultPolicyRequest $request): CollectionPolicyResult
    {
        $this->logger->log(
            [
                'message' => $request->clear ? 'Clearing sector default policy' : 'Setting sector default policy',
                'id' => $request->id,
                'sector' => $request->sector,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collectionpolicy'
        );

        $existing = $this->policyQuery->findById($request->id);
        if ($existing === null) {
            return CollectionPolicyResult::failure(['Policy not found.']);
        }

        if (trim($request->sector) === '') {
            return CollectionPolicyResult::failure(['Sector is required.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if ($request->clear) {
                $this->policyQuery->clearDefaultForSector($request->sector);
            } else {
                $this->policyQuery->setDefaultForSector($request->id, $request->sector);
            }

            // Update audit field
            $this->policyQuery->update($request->id, [
                'updated_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $policy = $this->policyQuery->findById($request->id);

            $this->logger->log(
                [
                    'message' => $request->clear ? 'Sector default cleared' : 'Sector default set',
                    'id' => $request->id,
                    'sector' => $request->sector,
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
                    'message' => 'Failed to set sector default',
                    'id' => $request->id,
                    'sector' => $request->sector,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collectionpolicy'
            );

            return CollectionPolicyResult::failure(['Failed to set default: ' . $e->getMessage()]);
        }
    }
}
