<?php

declare(strict_types=1);

namespace tests\unit\handlers\industryconfig;

use app\dto\industryconfig\CreateIndustryConfigRequest;
use app\handlers\industryconfig\CreateIndustryConfigHandler;
use app\models\IndustryConfig;
use Codeception\Test\Unit;
use yii\log\Logger;

/**
 * @covers \app\handlers\industryconfig\CreateIndustryConfigHandler
 */
final class CreateIndustryConfigHandlerTest extends Unit
{
    private Logger $logger;
    private CreateIndustryConfigHandler $handler;

    protected function _before(): void
    {
        // Refresh table schema to pick up any new columns
        $tableName = \Yii::$app->db->getSchema()->getRawTableName(IndustryConfig::tableName());
        \Yii::$app->db->getSchema()->refreshTableSchema($tableName);

        // Also refresh the ActiveRecord's internal cache
        IndustryConfig::getDb()->getSchema()->refresh();

        $this->logger = $this->createMock(Logger::class);
        $this->handler = new CreateIndustryConfigHandler($this->logger);

        \Yii::$app->db->createCommand()->delete($tableName)->execute();
    }

    protected function _after(): void
    {
        IndustryConfig::deleteAll();
    }

    public function testCreateSucceedsWithValidConfig(): void
    {
        $request = new CreateIndustryConfigRequest(
            industryId: 'test_oil',
            configJson: $this->createValidConfigJson('test_oil', 'Test Oil Industry'),
            actorUsername: 'admin',
            isActive: true,
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->config);
        $this->assertSame('test_oil', $result->config->industryId);
        $this->assertSame('Test Oil Industry', $result->config->name);
        $this->assertTrue($result->config->isActive);
        $this->assertSame('admin', $result->config->createdBy);
        $this->assertSame('admin', $result->config->updatedBy);
        $this->assertEmpty($result->errors);
    }

    public function testCreateFailsWithDuplicateIndustryId(): void
    {
        $config = new IndustryConfig();
        $config->industry_id = 'duplicate_id';
        $config->name = 'Existing';
        $config->config_json = $this->createValidConfigJson('duplicate_id', 'Existing');
        $config->is_active = true;
        $config->save(false);

        $request = new CreateIndustryConfigRequest(
            industryId: 'duplicate_id',
            configJson: $this->createValidConfigJson('duplicate_id', 'New Name'),
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->config);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('taken', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithInvalidJson(): void
    {
        $request = new CreateIndustryConfigRequest(
            industryId: 'bad_json',
            configJson: '{invalid json}',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->config);
        $this->assertNotEmpty($result->errors);
    }

    public function testCreateFailsWithMissingNameInJson(): void
    {
        $request = new CreateIndustryConfigRequest(
            industryId: 'no_name',
            configJson: '{"id": "no_name"}',
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('name', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateFailsWithIdMismatch(): void
    {
        $request = new CreateIndustryConfigRequest(
            industryId: 'correct_id',
            configJson: $this->createValidConfigJson('wrong_id', 'Test'),
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('must match', strtolower(implode(' ', $result->errors)));
    }

    public function testCreateSetsAuditFields(): void
    {
        $request = new CreateIndustryConfigRequest(
            industryId: 'audit_test',
            configJson: $this->createValidConfigJson('audit_test', 'Audit Test'),
            actorUsername: 'test_user',
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertSame('test_user', $result->config->createdBy);
        $this->assertSame('test_user', $result->config->updatedBy);

        $model = IndustryConfig::find()->where(['industry_id' => 'audit_test'])->one();
        $this->assertSame('test_user', $model->created_by);
        $this->assertSame('test_user', $model->updated_by);
    }

    public function testCreateDerivesNameFromConfigJson(): void
    {
        $request = new CreateIndustryConfigRequest(
            industryId: 'derive_name',
            configJson: $this->createValidConfigJson('derive_name', 'Derived Name From JSON'),
            actorUsername: 'admin',
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertSame('Derived Name From JSON', $result->config->name);
    }

    private function createValidConfigJson(string $id, string $name): string
    {
        return json_encode([
            'id' => $id,
            'name' => $name,
            'sector' => 'Energy',
            'companies' => [
                [
                    'ticker' => 'XOM',
                    'name' => 'ExxonMobil',
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
        ], JSON_THROW_ON_ERROR);
    }
}
