<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\ValidationResult;
use app\models\IndustryConfig;
use app\queries\IndustryConfigListQuery;
use app\validators\SchemaValidatorInterface;
use Codeception\Test\Unit;

/**
 * @covers \app\queries\IndustryConfigListQuery
 */
final class IndustryConfigListQueryTest extends Unit
{
    private IndustryConfigListQuery $query;
    private SchemaValidatorInterface $schemaValidator;

    protected function _before(): void
    {
        $tableName = \Yii::$app->db->getSchema()->getRawTableName(IndustryConfig::tableName());
        \Yii::$app->db->getSchema()->refreshTableSchema($tableName);
        IndustryConfig::getDb()->getSchema()->refresh();

        $this->schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $this->schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: true, errors: [])
        );
        $this->query = new IndustryConfigListQuery($this->schemaValidator);

        \Yii::$app->db->createCommand()->delete($tableName)->execute();
    }

    protected function _after(): void
    {
        IndustryConfig::deleteAll();
    }

    public function testListReturnsAllConfigs(): void
    {
        $this->createConfig('industry_a', 'Alpha Industry', true);
        $this->createConfig('industry_b', 'Beta Industry', false);

        $result = $this->query->list();

        $this->assertSame(2, $result->total);
        $this->assertCount(2, $result->items);
    }

    public function testListFiltersActiveOnly(): void
    {
        $this->createConfig('active_one', 'Active One', true);
        $this->createConfig('inactive_one', 'Inactive One', false);

        $result = $this->query->list(isActive: true);

        $this->assertSame(1, $result->total);
        $this->assertSame('active_one', $result->items[0]->industryId);
    }

    public function testListFiltersInactiveOnly(): void
    {
        $this->createConfig('active_one', 'Active One', true);
        $this->createConfig('inactive_one', 'Inactive One', false);

        $result = $this->query->list(isActive: false);

        $this->assertSame(1, $result->total);
        $this->assertSame('inactive_one', $result->items[0]->industryId);
    }

    public function testListSearchesByIndustryId(): void
    {
        $this->createConfig('oil_industry', 'Oil Industry', true);
        $this->createConfig('gas_industry', 'Gas Industry', true);

        $result = $this->query->list(search: 'oil');

        $this->assertSame(1, $result->total);
        $this->assertSame('oil_industry', $result->items[0]->industryId);
    }

    public function testListSearchesByName(): void
    {
        $this->createConfig('industry_1', 'Petroleum Sector', true);
        $this->createConfig('industry_2', 'Mining Sector', true);

        $result = $this->query->list(search: 'Petroleum');

        $this->assertSame(1, $result->total);
        $this->assertSame('industry_1', $result->items[0]->industryId);
    }

    public function testListOrdersByName(): void
    {
        $this->createConfig('industry_z', 'Zebra', true);
        $this->createConfig('industry_a', 'Alpha', true);

        $result = $this->query->list(orderBy: 'name', orderDirection: 'ASC');

        $this->assertSame('Alpha', $result->items[0]->name);
        $this->assertSame('Zebra', $result->items[1]->name);
    }

    public function testListOrdersDescending(): void
    {
        $this->createConfig('industry_z', 'Zebra', true);
        $this->createConfig('industry_a', 'Alpha', true);

        $result = $this->query->list(orderBy: 'name', orderDirection: 'DESC');

        $this->assertSame('Zebra', $result->items[0]->name);
        $this->assertSame('Alpha', $result->items[1]->name);
    }

    public function testListIgnoresInvalidOrderColumn(): void
    {
        $this->createConfig('industry_a', 'Alpha', true);

        $result = $this->query->list(orderBy: 'invalid_column');

        $this->assertSame(1, $result->total);
    }

    public function testFindByIndustryIdReturnsConfig(): void
    {
        $this->createConfig('find_me', 'Find Me', true);

        $result = $this->query->findByIndustryId('find_me');

        $this->assertNotNull($result);
        $this->assertSame('find_me', $result->industryId);
        $this->assertSame('Find Me', $result->name);
    }

    public function testFindByIndustryIdReturnsNullWhenNotFound(): void
    {
        $result = $this->query->findByIndustryId('nonexistent');

        $this->assertNull($result);
    }

    public function testFindByIndustryIdReturnsInactiveConfigs(): void
    {
        $this->createConfig('inactive_find', 'Inactive Find', false);

        $result = $this->query->findByIndustryId('inactive_find');

        $this->assertNotNull($result);
        $this->assertFalse($result->isActive);
    }

    public function testExistsReturnsTrueWhenExists(): void
    {
        $this->createConfig('exists_test', 'Exists Test', true);

        $this->assertTrue($this->query->exists('exists_test'));
    }

    public function testExistsReturnsFalseWhenNotExists(): void
    {
        $this->assertFalse($this->query->exists('nonexistent'));
    }

    public function testGetCountsReturnsCorrectCounts(): void
    {
        $this->createConfig('active_1', 'Active 1', true);
        $this->createConfig('active_2', 'Active 2', true);
        $this->createConfig('inactive_1', 'Inactive 1', false);

        $counts = $this->query->getCounts();

        $this->assertSame(3, $counts['total']);
        $this->assertSame(2, $counts['active']);
        $this->assertSame(1, $counts['inactive']);
    }

    public function testListReturnsEmptyWhenNoConfigs(): void
    {
        $result = $this->query->list();

        $this->assertSame(0, $result->total);
        $this->assertEmpty($result->items);
    }

    public function testResponseIncludesAuditFields(): void
    {
        $this->createConfig('audit_test', 'Audit Test', true, 'test_user');

        $result = $this->query->findByIndustryId('audit_test');

        $this->assertNotNull($result);
        $this->assertSame('test_user', $result->createdBy);
        $this->assertSame('test_user', $result->updatedBy);
    }

    public function testResponseIncludesIsJsonValidTrue(): void
    {
        $this->createConfig('valid_json', 'Valid JSON', true);

        $result = $this->query->findByIndustryId('valid_json');

        $this->assertNotNull($result);
        $this->assertTrue($result->isJsonValid);
    }

    public function testResponseIncludesIsJsonValidFalseForInvalidSchema(): void
    {
        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: false, errors: ['Missing required field'])
        );
        $query = new IndustryConfigListQuery($schemaValidator);

        $this->createConfig('invalid_schema', 'Invalid Schema', true);

        $result = $query->findByIndustryId('invalid_schema');

        $this->assertNotNull($result);
        $this->assertFalse($result->isJsonValid);
    }

    public function testResponseIncludesIsJsonValidFalseForInvalidJson(): void
    {
        $config = new IndustryConfig();
        $config->industry_id = 'bad_json';
        $config->name = 'Bad JSON';
        $config->config_json = '{invalid json}';
        $config->is_active = true;
        $config->save(false);

        $result = $this->query->findByIndustryId('bad_json');

        $this->assertNotNull($result);
        $this->assertFalse($result->isJsonValid);
    }

    private function createConfig(
        string $industryId,
        string $name,
        bool $isActive,
        string $createdBy = 'system'
    ): void {
        $config = new IndustryConfig();
        $config->industry_id = $industryId;
        $config->name = $name;
        $config->config_json = json_encode([
            'id' => $industryId,
            'name' => $name,
            'sector' => 'Test',
            'companies' => [
                [
                    'ticker' => 'TEST',
                    'name' => 'Test Company',
                    'listing_exchange' => 'NYSE',
                    'listing_currency' => 'USD',
                    'reporting_currency' => 'USD',
                    'fy_end_month' => 12,
                ],
            ],
            'macro_requirements' => new \stdClass(),
            'data_requirements' => [
                'history_years' => 5,
                'quarters_to_fetch' => 4,
                'valuation_metrics' => [],
            ],
        ]);
        $config->is_active = $isActive;
        $config->created_by = $createdBy;
        $config->updated_by = $createdBy;
        $config->save(false);
    }
}
