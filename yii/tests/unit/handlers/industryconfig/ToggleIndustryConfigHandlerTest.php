<?php

declare(strict_types=1);

namespace tests\unit\handlers\industryconfig;

use app\dto\industryconfig\ToggleIndustryConfigRequest;
use app\handlers\industryconfig\ToggleIndustryConfigHandler;
use app\models\IndustryConfig;
use Codeception\Test\Unit;
use yii\log\Logger;

/**
 * @covers \app\handlers\industryconfig\ToggleIndustryConfigHandler
 */
final class ToggleIndustryConfigHandlerTest extends Unit
{
    private Logger $logger;
    private ToggleIndustryConfigHandler $handler;

    protected function _before(): void
    {
        // Refresh table schema to pick up any new columns
        \Yii::$app->db->getSchema()->refreshTableSchema(IndustryConfig::tableName());

        $this->logger = $this->createMock(Logger::class);
        $this->handler = new ToggleIndustryConfigHandler($this->logger);

        IndustryConfig::deleteAll();
    }

    protected function _after(): void
    {
        IndustryConfig::deleteAll();
    }

    public function testToggleFromActiveToInactive(): void
    {
        $this->createExistingConfig('toggle_off', 'Toggle Test', true);

        $request = new ToggleIndustryConfigRequest(
            industryId: 'toggle_off',
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertFalse($result->config->isActive);
        $this->assertSame('admin', $result->config->updatedBy);
    }

    public function testToggleFromInactiveToActive(): void
    {
        $this->createExistingConfig('toggle_on', 'Toggle Test', false);

        $request = new ToggleIndustryConfigRequest(
            industryId: 'toggle_on',
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertTrue($result->config->isActive);
    }

    public function testToggleFailsWhenNotFound(): void
    {
        $request = new ToggleIndustryConfigRequest(
            industryId: 'nonexistent',
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertFalse($result->success);
        $this->assertNull($result->config);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('not found', strtolower(implode(' ', $result->errors)));
    }

    public function testToggleWorksWithInvalidJson(): void
    {
        $config = new IndustryConfig();
        $config->industry_id = 'invalid_json_toggle';
        $config->name = 'Invalid JSON Config';
        $config->config_json = '{invalid json that should not prevent toggle}';
        $config->is_active = true;
        $config->save(false);

        $request = new ToggleIndustryConfigRequest(
            industryId: 'invalid_json_toggle',
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertFalse($result->config->isActive);
    }

    public function testToggleDoesNotValidateSchema(): void
    {
        $config = new IndustryConfig();
        $config->industry_id = 'schema_invalid_toggle';
        $config->name = 'Schema Invalid Config';
        $config->config_json = json_encode(['id' => 'schema_invalid_toggle', 'name' => 'Missing fields']);
        $config->is_active = true;
        $config->save(false);

        $request = new ToggleIndustryConfigRequest(
            industryId: 'schema_invalid_toggle',
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertFalse($result->config->isActive);
    }

    public function testToggleStampsUpdatedBy(): void
    {
        $this->createExistingConfig('audit_toggle', 'Audit Test', true, 'original_user');

        $request = new ToggleIndustryConfigRequest(
            industryId: 'audit_toggle',
            actorUsername: 'toggler',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertSame('toggler', $result->config->updatedBy);
        $this->assertSame('original_user', $result->config->createdBy);
    }

    public function testTogglePreservesOtherFields(): void
    {
        $this->createExistingConfig('preserve_toggle', 'Preserve Test', true);

        $originalModel = IndustryConfig::find()->where(['industry_id' => 'preserve_toggle'])->one();
        $originalJson = $originalModel->config_json;
        $originalName = $originalModel->name;

        $request = new ToggleIndustryConfigRequest(
            industryId: 'preserve_toggle',
            actorUsername: 'admin',
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertSame($originalJson, $result->config->configJson);
        $this->assertSame($originalName, $result->config->name);
    }

    private function createExistingConfig(
        string $industryId,
        string $name,
        bool $isActive,
        string $createdBy = 'system'
    ): IndustryConfig {
        $config = new IndustryConfig();
        $config->industry_id = $industryId;
        $config->name = $name;
        $config->config_json = $this->createValidConfigJson($industryId, $name);
        $config->is_active = $isActive;
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
