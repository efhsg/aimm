<?php

declare(strict_types=1);

namespace app\models\query;

use app\models\IndustryConfig;
use yii\db\ActiveQuery;

/**
 * ActiveQuery class for IndustryConfig.
 *
 * @method IndustryConfig[] all($db = null)
 * @method IndustryConfig|null one($db = null)
 */
final class IndustryConfigQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['is_active' => true]);
    }

    public function inactive(): self
    {
        return $this->andWhere(['is_active' => false]);
    }

    public function byIndustryId(string $industryId): self
    {
        return $this->andWhere(['industry_id' => $industryId]);
    }
}
