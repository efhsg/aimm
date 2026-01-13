<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\models\CollectionAttempt;
use app\queries\CollectionAttemptQuery;
use Codeception\Test\Unit;

final class CollectionAttemptQueryTest extends Unit
{
    public function testFindRecentByEntityConfiguration(): void
    {
        $query = new CollectionAttemptQuery(CollectionAttempt::class);
        $query->findRecentByEntity('company', 1, 5);

        $this->assertEquals(['entity_type' => 'company'], $query->where[1]);
        $this->assertEquals(['entity_id' => 1], $query->where[2]);
        $this->assertEquals(['attempted_at' => SORT_DESC], $query->orderBy);
        $this->assertEquals(5, $query->limit);
    }
}
