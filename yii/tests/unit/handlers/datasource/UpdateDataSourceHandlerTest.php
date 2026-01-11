<?php

declare(strict_types=1);

namespace tests\unit\handlers\datasource;

use app\dto\datasource\UpdateDataSourceRequest;
use app\handlers\datasource\UpdateDataSourceHandler;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Codeception\Test\Unit;
use Yii;

final class UpdateDataSourceHandlerTest extends Unit
{
    private UpdateDataSourceHandler $handler;

    protected function _before(): void
    {
        DataSource::deleteAll();
        $this->handler = new UpdateDataSourceHandler(
            Yii::$app->db,
            new DataSourceQuery(Yii::$app->db),
        );

        $model = new DataSource();
        $model->id = 'test_api';
        $model->name = 'Original Name';
        $model->source_type = 'api';
        $model->save(false);
    }

    public function testUpdateSucceeds(): void
    {
        $request = new UpdateDataSourceRequest(
            id: 'test_api',
            name: 'Updated Name',
            sourceType: 'web_scrape',
            actorUsername: 'tester',
            baseUrl: 'https://updated.com',
            notes: 'Updated notes'
        );

        $result = $this->handler->update($request);

        $this->assertTrue($result->success);

        $model = DataSource::findOne('test_api');
        $this->assertEquals('Updated Name', $model->name);
        $this->assertEquals('web_scrape', $model->source_type);
        $this->assertEquals('https://updated.com', $model->base_url);
        $this->assertEquals('Updated notes', $model->notes);
    }

    public function testUpdateFailsIfNotFound(): void
    {
        $request = new UpdateDataSourceRequest(
            id: 'missing',
            name: 'Updated Name',
            sourceType: 'api',
            actorUsername: 'tester'
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertContains('Data source not found.', $result->errors);
    }

    public function testUpdateFailsWithInvalidData(): void
    {
        $request = new UpdateDataSourceRequest(
            id: 'test_api',
            name: '', // Empty name not allowed
            sourceType: 'api',
            actorUsername: 'tester'
        );

        $result = $this->handler->update($request);

        $this->assertFalse($result->success);
        $this->assertContains('Name cannot be blank.', $result->errors);
    }
}
