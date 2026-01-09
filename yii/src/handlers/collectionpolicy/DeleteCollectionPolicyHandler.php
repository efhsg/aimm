<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\DeleteCollectionPolicyRequest;
use app\queries\CollectionPolicyQuery;
use Throwable;
use Yii;
use yii\db\Connection;
use yii\log\Logger;

/**
 * Handler for deleting collection policies.
 */
final class DeleteCollectionPolicyHandler implements DeleteCollectionPolicyInterface
{
    public function __construct(
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly Connection $db,
        private readonly Logger $logger,
    ) {
    }

    public function delete(DeleteCollectionPolicyRequest $request): CollectionPolicyResult
    {
        $this->logger->log(
            [
                'message' => 'Deleting collection policy',
                'id' => $request->id,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collectionpolicy'
        );

        $existing = $this->policyQuery->findById($request->id);
        if ($existing === null) {
            return CollectionPolicyResult::failure(['Policy not found.']);
        }

        // Check if policy is in use by any industries
        $usageCount = $this->db->createCommand(
            'SELECT COUNT(*) FROM industry WHERE policy_id = :id'
        )
            ->bindValue(':id', $request->id)
            ->queryScalar();

        if ((int) $usageCount > 0) {
            return CollectionPolicyResult::failure([
                'Cannot delete policy: it is assigned to ' . $usageCount . ' industry(ies).',
            ]);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->policyQuery->delete($request->id);

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Collection policy deleted',
                    'id' => $request->id,
                    'slug' => $existing['slug'],
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'collectionpolicy'
            );

            return CollectionPolicyResult::deleted();
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to delete collection policy',
                    'id' => $request->id,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collectionpolicy'
            );

            return CollectionPolicyResult::failure(['Failed to delete policy: ' . $e->getMessage()]);
        }
    }
}
