<?php

declare(strict_types=1);

namespace app\queries;

use app\models\CollectionAttempt;
use yii\db\ActiveQuery;

/**
 * ActiveQuery for {@see CollectionAttempt}.
 *
 * @extends ActiveQuery<CollectionAttempt>
 */
final class CollectionAttemptQuery extends ActiveQuery
{
    /**
     * @return CollectionAttempt[]
     */
    public function findRecentByEntity(string $entityType, int $entityId, int $limit = 10): array
    {
        return $this->andWhere(['entity_type' => $entityType])
            ->andWhere(['entity_id' => $entityId])
            ->orderBy(['attempted_at' => SORT_DESC])
            ->limit($limit)
            ->all();
    }
}
