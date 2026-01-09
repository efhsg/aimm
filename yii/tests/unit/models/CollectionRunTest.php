<?php

declare(strict_types=1);

namespace tests\unit\models;

use app\models\CollectionError;
use app\models\CollectionRun;
use app\models\query\CollectionRunQuery;
use Codeception\Test\Unit;
use Ramsey\Uuid\Uuid;
use Yii;

/**
 * @covers \app\models\CollectionRun
 */
final class CollectionRunTest extends Unit
{
    private int $industryId;
    private int $sectorId;

    protected function _before(): void
    {
        // Clean up before each test
        CollectionError::deleteAll();
        CollectionRun::deleteAll();
        Yii::$app->db->createCommand()->delete('industry')->execute();
        Yii::$app->db->createCommand()->delete('sector')->execute();

        // Create required sector and industry
        Yii::$app->db->createCommand()->insert('sector', [
            'slug' => 'energy',
            'name' => 'Energy',
        ])->execute();
        $this->sectorId = (int) Yii::$app->db->getLastInsertID();

        Yii::$app->db->createCommand()->insert('industry', [
            'slug' => 'test-industry',
            'name' => 'Test Industry',
            'sector_id' => $this->sectorId,
            'is_active' => 1,
            'created_by' => 'test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        $this->industryId = (int) Yii::$app->db->getLastInsertID();
    }

    public function testValidationRequiresRequiredFields(): void
    {
        $model = new CollectionRun();

        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('industry_id', $model->getErrors());
        $this->assertArrayHasKey('datapack_id', $model->getErrors());
    }

    public function testValidationPassesWithRequiredFields(): void
    {
        $model = new CollectionRun();
        $model->industry_id = $this->industryId;
        $model->datapack_id = Uuid::uuid4()->toString();

        $this->assertTrue($model->validate());
    }

    public function testDatapackIdMustBeUnique(): void
    {
        $datapackId = Uuid::uuid4()->toString();

        $run1 = new CollectionRun();
        $run1->industry_id = $this->industryId;
        $run1->datapack_id = $datapackId;
        $run1->save();

        $run2 = new CollectionRun();
        $run2->industry_id = $this->industryId;
        $run2->datapack_id = $datapackId;

        $this->assertFalse($run2->validate());
        $this->assertArrayHasKey('datapack_id', $run2->getErrors());
    }

    public function testStatusDefaultsToPending(): void
    {
        $model = new CollectionRun();
        $model->industry_id = $this->industryId;
        $model->datapack_id = Uuid::uuid4()->toString();
        $model->save();

        $this->assertSame(CollectionRun::STATUS_PENDING, $model->status);
    }

    public function testStatusValidation(): void
    {
        $model = new CollectionRun();
        $model->industry_id = $this->industryId;
        $model->datapack_id = Uuid::uuid4()->toString();
        $model->status = 'invalid-status';

        $this->assertFalse($model->validate());
        $this->assertArrayHasKey('status', $model->getErrors());
    }

    public function testFindReturnsCollectionRunQuery(): void
    {
        $query = CollectionRun::find();

        $this->assertInstanceOf(CollectionRunQuery::class, $query);
    }

    public function testForIndustryScopeFiltersCorrectly(): void
    {
        $run = new CollectionRun();
        $run->industry_id = $this->industryId;
        $run->datapack_id = Uuid::uuid4()->toString();
        $run->save();

        $results = CollectionRun::find()->forIndustry($this->industryId)->all();

        $this->assertCount(1, $results);
    }

    public function testStatusScopesFilterCorrectly(): void
    {
        $pending = new CollectionRun();
        $pending->industry_id = $this->industryId;
        $pending->datapack_id = Uuid::uuid4()->toString();
        $pending->status = CollectionRun::STATUS_PENDING;
        $pending->save();

        $complete = new CollectionRun();
        $complete->industry_id = $this->industryId;
        $complete->datapack_id = Uuid::uuid4()->toString();
        $complete->status = CollectionRun::STATUS_COMPLETE;
        $complete->save();

        $this->assertCount(1, CollectionRun::find()->pending()->all());
        $this->assertCount(1, CollectionRun::find()->complete()->all());
        $this->assertCount(0, CollectionRun::find()->failed()->all());
    }

    public function testQueryChainingFiltersCorrectly(): void
    {
        // Create second industry for filter testing
        Yii::$app->db->createCommand()->insert('industry', [
            'slug' => 'other-industry',
            'name' => 'Other Industry',
            'sector_id' => $this->sectorId,
            'is_active' => 1,
            'created_by' => 'test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        $otherIndustryId = (int) Yii::$app->db->getLastInsertID();

        $completePassed = new CollectionRun();
        $completePassed->industry_id = $this->industryId;
        $completePassed->datapack_id = Uuid::uuid4()->toString();
        $completePassed->status = CollectionRun::STATUS_COMPLETE;
        $completePassed->gate_passed = true;
        $completePassed->save();

        $completeFailed = new CollectionRun();
        $completeFailed->industry_id = $this->industryId;
        $completeFailed->datapack_id = Uuid::uuid4()->toString();
        $completeFailed->status = CollectionRun::STATUS_COMPLETE;
        $completeFailed->gate_passed = false;
        $completeFailed->save();

        $otherIndustryRun = new CollectionRun();
        $otherIndustryRun->industry_id = $otherIndustryId;
        $otherIndustryRun->datapack_id = Uuid::uuid4()->toString();
        $otherIndustryRun->status = CollectionRun::STATUS_COMPLETE;
        $otherIndustryRun->gate_passed = true;
        $otherIndustryRun->save();

        $results = CollectionRun::find()
            ->forIndustry($this->industryId)
            ->complete()
            ->gatePassed()
            ->all();

        $this->assertCount(1, $results);
        $this->assertSame($completePassed->datapack_id, $results[0]->datapack_id);
    }

    public function testMarkRunningUpdatesStatus(): void
    {
        $run = new CollectionRun();
        $run->industry_id = $this->industryId;
        $run->datapack_id = Uuid::uuid4()->toString();
        $run->save();

        $run->markRunning();

        $this->assertSame(CollectionRun::STATUS_RUNNING, $run->status);
    }

    public function testMarkCompleteUpdatesStatusAndGatePassed(): void
    {
        $run = new CollectionRun();
        $run->industry_id = $this->industryId;
        $run->datapack_id = Uuid::uuid4()->toString();
        $run->save();

        $run->markComplete(true);

        $this->assertSame(CollectionRun::STATUS_COMPLETE, $run->status);
        $this->assertTrue((bool) $run->gate_passed);
        $this->assertNotNull($run->completed_at);
    }

    public function testMarkFailedUpdatesStatus(): void
    {
        $run = new CollectionRun();
        $run->industry_id = $this->industryId;
        $run->datapack_id = Uuid::uuid4()->toString();
        $run->save();

        $run->markFailed();

        $this->assertSame(CollectionRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->completed_at);
    }

    public function testHasManyCollectionErrors(): void
    {
        $run = new CollectionRun();
        $run->industry_id = $this->industryId;
        $run->datapack_id = Uuid::uuid4()->toString();
        $run->save();

        $error = CollectionError::createError(
            $run->id,
            'TEST_ERROR',
            'Test error message'
        );
        $error->save();

        $run->refresh();
        $errors = $run->collectionErrors;

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(CollectionError::class, $errors[0]);
    }

    public function testGatePassedScopeFiltersCorrectly(): void
    {
        $passed = new CollectionRun();
        $passed->industry_id = $this->industryId;
        $passed->datapack_id = Uuid::uuid4()->toString();
        $passed->gate_passed = true;
        $passed->save();

        $failed = new CollectionRun();
        $failed->industry_id = $this->industryId;
        $failed->datapack_id = Uuid::uuid4()->toString();
        $failed->gate_passed = false;
        $failed->save();

        $this->assertCount(1, CollectionRun::find()->gatePassed()->all());
        $this->assertCount(1, CollectionRun::find()->gateFailed()->all());
    }

    public function testRecentScopeOrdersByStartedAtDescending(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $run = new CollectionRun();
            $run->industry_id = $this->industryId;
            $run->datapack_id = Uuid::uuid4()->toString();
            $run->save();
        }

        $results = CollectionRun::find()->recent(3)->all();

        $this->assertCount(3, $results);
    }
}
