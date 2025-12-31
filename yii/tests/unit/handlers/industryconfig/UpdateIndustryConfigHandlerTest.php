<?php

declare(strict_types=1);

namespace tests\unit\handlers\industryconfig;

use app\dto\industryconfig\UpdateIndustryConfigRequest;
use app\handlers\industryconfig\UpdateIndustryConfigHandler;
use app\models\IndustryConfig;
use Codeception\Test\Unit;
use yii\log\Logger;

/**
 * @covers \app\handlers\industryconfig\UpdateIndustryConfigHandler
 */
final class UpdateIndustryConfigHandlerTest extends Unit
{
    private Logger $logger;
    private UpdateIndustryConfigHandler $handler;

    protected function _before(): void
    {
        // Refresh table schema to pick up any new columns
        \Yii::$app->db->getSchema()->refreshTableSchema(IndustryConfig::tableName());

        $this->logger = $this->createMock(Logger::class);
        $this->handler = new UpdateIndustryConfigHandler($this->logger);

        IndustryConfig::deleteAll();
    }

    protected function _after(): void
    {
        IndustryConfig::deleteAll();
    }

    public function testUpdateSucceedsWithValidConfig(): void
    {
        $this->createExistingConfig('update_test', 'Old Name');

        $request = new UpdateIndustryConfigRequest(
            industryId: 'update_test',
            configJson: $this->createValidConfigJson('update_test', 'New Name'),
            actorUsername: 'editor',
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->config);
        $this->assertSame('update_test', $result->config->industryId);
        $this->assertSame('New Name', $result->config->name);
        $this->assertSame('editor', $result->config->updatedBy);
        $this->assertEmpty($result->errors);
    }

    public function testUpdateFailsWhenNotFound(): void
    {
        $request = new UpdateIndustryConfigRequest(
            industryId: 'nonexistent',
            configJson: $this->createValidConfigJson('nonexistent', 'Test'),
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->config);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testUpdateFailsWithInvalidJson(): void
    {
        $this->createExistingConfig('invalid_json_test', 'Original');

        $request = new UpdateIndustryConfigRequest(
            industryId: 'invalid_json_test',
            configJson: '{invalid json}',
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
    }

    public function testUpdateFailsWithIdMismatch(): void
    {
        $this->createExistingConfig('mismatch_test', 'Original');

        $request = new UpdateIndustryConfigRequest(
            industryId: 'mismatch_test',
            configJson: $this->createValidConfigJson('different_id', 'New'),
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('must match', strtolower(implode(' ', $result->errors)));
    }

    public function testUpdatePreservesIndustryId(): void
    {
        $this->createExistingConfig('preserve_id', 'Original');

        $request = new UpdateIndustryConfigRequest(
            industryId: 'preserve_id',
            configJson: $this->createValidConfigJson('preserve_id', 'Updated Name'),
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertSame('preserve_id', $result->config->industryId);

        $model = IndustryConfig::find()->where(['industry_id' => 'preserve_id'])->one();
        $this->assertNotNull($model);
    }

    public function testUpdateStampsUpdatedBy(): void
    {
        $this->createExistingConfig('audit_update', 'Original', 'original_user');

        $request = new UpdateIndustryConfigRequest(
            industryId: 'audit_update',
            configJson: $this->createValidConfigJson('audit_update', 'Updated'),
            actorUsername: 'new_editor',
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertSame('new_editor', $result->config->updatedBy);
        $this->assertSame('original_user', $result->config->createdBy);
    }

    public function testUpdateDerivesNameFromConfigJson(): void
    {
        $this->createExistingConfig('derive_update', 'Old Name');

        $request = new UpdateIndustryConfigRequest(
            industryId: 'derive_update',
            configJson: $this->createValidConfigJson('derive_update', 'New Derived Name'),
            actorUsername: 'admin',
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);
        $this->assertSame('New Derived Name', $result->config->name);
    }

    private function createExistingConfig(
        string $industryId,
        string $name,
        string $createdBy = 'system'
    ): IndustryConfig {
        $config = new IndustryConfig();
        $config->industry_id = $industryId;
        $config->name = $name;
        $config->config_json = $this->createValidConfigJson($industryId, $name);
        $config->is_active = true;
        $config->created_by = $createdBy;
        $config->updated_by = $createdBy;
        $config->save(false);

        return $config;
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
