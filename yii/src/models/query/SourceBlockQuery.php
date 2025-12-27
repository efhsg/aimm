<?php

declare(strict_types=1);

namespace app\models\query;

use app\models\SourceBlock;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * ActiveQuery class for SourceBlock.
 *
 * @method SourceBlock[] all($db = null)
 * @method SourceBlock|null one($db = null)
 */
final class SourceBlockQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['>', 'blocked_until', new Expression('NOW()')]);
    }

    public function expired(): self
    {
        return $this->andWhere(['<=', 'blocked_until', new Expression('NOW()')]);
    }

    public function forDomain(string $domain): self
    {
        return $this->andWhere(['domain' => $domain]);
    }

    public function byStatusCode(int $statusCode): self
    {
        return $this->andWhere(['last_status_code' => $statusCode]);
    }

    public function highConsecutiveCount(int $threshold = 3): self
    {
        return $this->andWhere(['>=', 'consecutive_count', $threshold]);
    }
}
