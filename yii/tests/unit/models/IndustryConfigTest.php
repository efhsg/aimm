<?php

declare(strict_types=1);

namespace tests\unit\models;

use app\models\CollectionRun;
use app\models\IndustryConfig;
use app\models\query\IndustryConfigQuery;
use Codeception\Test\Unit;
use Ramsey\Uuid\Uuid;

/**
 * @covers \app\models\IndustryConfig
 */
final class IndustryConfigTest extends Unit
{
    protected function _before(): void
    {
        // Clean up before each test
        IndustryConfig::deleteAll();
    }

    public function testValidationRequiresRequiredFields(): void
    {
        $model = new IndustryConfig();

        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('industry_id', $model->getErrors());
        // name is not required - it is derived from config_json by handlers
        $this->assertArrayHasKey('config_json', $model->getErrors());
    }

    public function testValidationPassesWithRequiredFields(): void
    {
        $model = new IndustryConfig();
        $model->industry_id = 'oil-majors';
        $model->name = 'Oil Majors';
        $model->config_json = 'companies: []';

        $this->assertTrue($model->validate());
    }

    public function testIndustryIdMustBeUnique(): void
    {
        $model1 = new IndustryConfig();
        $model1->industry_id = 'test-industry';
        $model1->name = 'Test Industry';
        $model1->config_json = 'companies: []';
        $model1->save();

        $model2 = new IndustryConfig();
        $model2->industry_id = 'test-industry';
        $model2->name = 'Test Industry 2';
        $model2->config_json = 'companies: []';

        $this->assertFalse($model2->validate());
        $this->assertArrayHasKey('industry_id', $model2->getErrors());
    }

    public function testIsActiveDefaultsToTrue(): void
    {
        $model = new IndustryConfig();
        $model->industry_id = 'test-industry';
        $model->name = 'Test Industry';
        $model->config_json = 'companies: []';
        $model->save();

        $this->assertTrue((bool) $model->is_active);
    }

    public function testFindReturnsIndustryConfigQuery(): void
    {
        $query = IndustryConfig::find();

        $this->assertInstanceOf(IndustryConfigQuery::class, $query);
    }

    public function testActiveScopeFiltersActiveRecords(): void
    {
        $active = new IndustryConfig();
        $active->industry_id = 'active-industry';
        $active->name = 'Active Industry';
        $active->config_json = 'companies: []';
        $active->is_active = true;
        $active->save();

        $inactive = new IndustryConfig();
        $inactive->industry_id = 'inactive-industry';
        $inactive->name = 'Inactive Industry';
        $inactive->config_json = 'companies: []';
        $inactive->is_active = false;
        $inactive->save();

        $activeResults = IndustryConfig::find()->active()->all();
        $inactiveResults = IndustryConfig::find()->inactive()->all();

        $this->assertCount(1, $activeResults);
        $this->assertCount(1, $inactiveResults);
        $this->assertSame('active-industry', $activeResults[0]->industry_id);
        $this->assertSame('inactive-industry', $inactiveResults[0]->industry_id);
    }

    public function testHasManyCollectionRuns(): void
    {
        $config = new IndustryConfig();
        $config->industry_id = 'test-industry';
        $config->name = 'Test Industry';
        $config->config_json = 'companies: []';
        $config->save();

        $run = new CollectionRun();
        $run->industry_id = 'test-industry';
        $run->datapack_id = Uuid::uuid4()->toString();
        $run->save();

        $config->refresh();
        $runs = $config->collectionRuns;

        $this->assertCount(1, $runs);
        $this->assertInstanceOf(CollectionRun::class, $runs[0]);
    }

    public function testByIndustryIdScopeFiltersCorrectly(): void
    {
        $config = new IndustryConfig();
        $config->industry_id = 'specific-industry';
        $config->name = 'Specific Industry';
        $config->config_json = 'companies: []';
        $config->save();

        $result = IndustryConfig::find()->byIndustryId('specific-industry')->one();

        $this->assertNotNull($result);
        $this->assertSame('specific-industry', $result->industry_id);
    }
}
