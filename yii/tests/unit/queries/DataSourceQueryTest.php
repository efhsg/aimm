<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\queries\DataSourceQuery;
use Codeception\Test\Unit;
use Yii;

/**
 * Tests for DataSourceQuery standalone query class.
 */
final class DataSourceQueryTest extends Unit
{
    private DataSourceQuery $query;

    protected function _before(): void
    {
        $this->query = new DataSourceQuery(Yii::$app->db);

        // Clear and insert test data
        Yii::$app->db->createCommand()->delete('data_source')->execute();
        Yii::$app->db->createCommand()->batchInsert(
            'data_source',
            ['id', 'name', 'source_type', 'is_active', 'base_url', 'notes'],
            [
                ['api_1', 'API Source 1', 'api', 1, 'https://api1.com', 'Note 1'],
                ['api_2', 'API Source 2', 'api', 0, 'https://api2.com', null],
                ['web_1', 'Web Source 1', 'web_scrape', 1, null, 'Note 2'],
                ['derived_1', 'Derived Source 1', 'derived', 1, null, null],
            ]
        )->execute();
    }

    public function testFindAllReturnsArray(): void
    {
        $result = $this->query->findAll();

        $this->assertCount(4, $result);
        $this->assertIsArray($result[0]);
    }

    public function testFindByIdReturnsDataSource(): void
    {
        $result = $this->query->findById('api_1');

        $this->assertNotNull($result);
        $this->assertEquals('api_1', $result['id']);
        $this->assertEquals('API Source 1', $result['name']);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $result = $this->query->findById('missing');

        $this->assertNull($result);
    }

    public function testListFiltersActiveCorrectly(): void
    {
        $result = $this->query->list('active');

        $this->assertCount(3, $result);
        foreach ($result as $row) {
            $this->assertEquals(1, $row['is_active']);
        }
    }

    public function testListFiltersInactiveCorrectly(): void
    {
        $result = $this->query->list('inactive');

        $this->assertCount(1, $result);
        $this->assertEquals('api_2', $result[0]['id']);
    }

    public function testListFiltersByType(): void
    {
        $result = $this->query->list(null, 'api');
        $this->assertCount(2, $result);

        $result = $this->query->list(null, 'web_scrape');
        $this->assertCount(1, $result);
        $this->assertEquals('web_1', $result[0]['id']);
    }

    public function testListFiltersBySearch(): void
    {
        $result = $this->query->list(null, null, 'API');

        $this->assertCount(2, $result);
    }

    public function testGetCountsReturnsCorrectCounts(): void
    {
        $counts = $this->query->getCounts();

        $this->assertEquals(4, $counts['total']);
        $this->assertEquals(3, $counts['active']);
        $this->assertEquals(1, $counts['inactive']);
    }

    public function testFindPoliciesUsingSourceReturnsEmptyWhenNotUsed(): void
    {
        $result = $this->query->findPoliciesUsingSource('api_1');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
