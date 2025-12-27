<?php

declare(strict_types=1);

namespace app\models\query;

use app\models\CollectionError;
use yii\db\ActiveQuery;

/**
 * ActiveQuery class for CollectionError.
 *
 * @method CollectionError[] all($db = null)
 * @method CollectionError|null one($db = null)
 */
final class CollectionErrorQuery extends ActiveQuery
{
    public function forRun(int $collectionRunId): self
    {
        return $this->andWhere(['collection_run_id' => $collectionRunId]);
    }

    public function errors(): self
    {
        return $this->andWhere(['severity' => CollectionError::SEVERITY_ERROR]);
    }

    public function warnings(): self
    {
        return $this->andWhere(['severity' => CollectionError::SEVERITY_WARNING]);
    }

    public function forTicker(string $ticker): self
    {
        return $this->andWhere(['ticker' => $ticker]);
    }

    public function byCode(string $errorCode): self
    {
        return $this->andWhere(['error_code' => $errorCode]);
    }

    public function recent(int $limit = 50): self
    {
        return $this->orderBy(['created_at' => SORT_DESC])->limit($limit);
    }
}
