<?php

declare(strict_types=1);

namespace tests\unit\handlers\datasource;

use app\dto\datasource\ToggleDataSourceRequest;
use app\handlers\datasource\ToggleDataSourceHandler;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Codeception\Test\Unit;
use Yii;

final class ToggleDataSourceHandlerTest extends Unit
{
    private ToggleDataSourceHandler $handler;

    protected function _before(): void
    {
        DataSource::deleteAll();
        $this->handler = new ToggleDataSourceHandler(
            Yii::$app->db,
            new DataSourceQuery(Yii::$app->db),
        );

        $model = new DataSource();
        $model->id = 'test_api';
        $model->name = 'Test API';
        $model->source_type = 'api';
        $model->is_active = 1;
        $model->save(false);
    }

    public function testToggleDeactivatesActiveSource(): void
    {
        $request = new ToggleDataSourceRequest(
            id: 'test_api',
            actorUsername: 'tester'
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->dataSource['is_active']);

        $model = DataSource::findOne('test_api');
        $this->assertEquals(0, $model->is_active);
    }

    public function testToggleActivatesInactiveSource(): void
    {
        // Set to inactive first
        $model = DataSource::findOne('test_api');
        $model->is_active = 0;
        $model->save(false);

        $request = new ToggleDataSourceRequest(
            id: 'test_api',
            actorUsername: 'tester'
        );

        $result = $this->handler->toggle($request);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->dataSource['is_active']);
    }

    public function testToggleFailsIfNotFound(): void
    {
        $request = new ToggleDataSourceRequest(
            id: 'missing',
            actorUsername: 'tester'
        );

        $result = $this->handler->toggle($request);

        $this->assertFalse($result->success);
        $this->assertContains('Data source not found.', $result->errors);
    }
}
