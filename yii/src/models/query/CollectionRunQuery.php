<?php

declare(strict_types=1);

namespace app\models\query;

use app\models\CollectionRun;
use yii\db\ActiveQuery;

/**
 * ActiveQuery class for CollectionRun.
 *
 * @method CollectionRun[] all($db = null)
 * @method CollectionRun|null one($db = null)
 */
final class CollectionRunQuery extends ActiveQuery
{
    public function forIndustry(int $industryId): self
    {
        return $this->andWhere(['industry_id' => $industryId]);
    }

    public function pending(): self
    {
        return $this->andWhere(['status' => CollectionRun::STATUS_PENDING]);
    }

    public function running(): self
    {
        return $this->andWhere(['status' => CollectionRun::STATUS_RUNNING]);
    }

    public function complete(): self
    {
        return $this->andWhere(['status' => CollectionRun::STATUS_COMPLETE]);
    }

    public function partial(): self
    {
        return $this->andWhere(['status' => CollectionRun::STATUS_PARTIAL]);
    }

    public function failed(): self
    {
        return $this->andWhere(['status' => CollectionRun::STATUS_FAILED]);
    }

    public function gatePassed(): self
    {
        return $this->andWhere(['gate_passed' => true]);
    }

    public function gateFailed(): self
    {
        return $this->andWhere(['gate_passed' => false]);
    }

    public function byDatapackId(string $datapackId): self
    {
        return $this->andWhere(['datapack_id' => $datapackId]);
    }

    public function recent(int $limit = 10): self
    {
        return $this->orderBy(['started_at' => SORT_DESC])->limit($limit);
    }

    public function withErrors(): self
    {
        return $this->andWhere(['>', 'error_count', 0]);
    }

    public function withWarnings(): self
    {
        return $this->andWhere(['>', 'warning_count', 0]);
    }
}
