<?php

declare(strict_types=1);

namespace tests\unit\handlers\datasource;

use app\dto\datasource\CreateDataSourceRequest;
use app\handlers\datasource\CreateDataSourceHandler;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Codeception\Test\Unit;
use Yii;

final class CreateDataSourceHandlerTest extends Unit
{
    private CreateDataSourceHandler $handler;

    protected function _before(): void
    {
        DataSource::deleteAll();
        $this->handler = new CreateDataSourceHandler(
            Yii::$app->db,
            new DataSourceQuery(Yii::$app->db),
        );
    }

    public function testCreateSucceeds(): void
    {
        $request = new CreateDataSourceRequest(
            id: 'test_api',
            name: 'Test API',
            sourceType: 'api',
            actorUsername: 'tester',
            baseUrl: 'https://example.com',
            notes: 'Test notes'
        );

        $result = $this->handler->create($request);

        $this->assertTrue($result->success);
        $this->assertEquals('test_api', $result->dataSource['id']);

        $model = DataSource::findOne('test_api');
        $this->assertNotNull($model);
        $this->assertEquals('Test API', $model->name);
        $this->assertEquals(1, $model->is_active);
    }

    public function testCreateFailsWithDuplicateId(): void
    {
        $request = new CreateDataSourceRequest(
            id: 'dup_api',
            name: 'First API',
            sourceType: 'api',
            actorUsername: 'tester'
        );
        $this->handler->create($request);

        $request2 = new CreateDataSourceRequest(
            id: 'dup_api',
            name: 'Second API',
            sourceType: 'api',
            actorUsername: 'tester'
        );
        $result = $this->handler->create($request2);

        $this->assertFalse($result->success);
        $this->assertContains('ID "dup_api" has already been taken.', $result->errors);
    }

    public function testCreateFailsWithInvalidType(): void
    {
        $request = new CreateDataSourceRequest(
            id: 'invalid_type',
            name: 'Invalid Type',
            sourceType: 'magic',
            actorUsername: 'tester'
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertContains('Source Type is invalid.', $result->errors);
    }

    public function testCreateFailsWithInvalidUrl(): void
    {
        $request = new CreateDataSourceRequest(
            id: 'invalid_url',
            name: 'Invalid URL',
            sourceType: 'api',
            actorUsername: 'tester',
            baseUrl: 'not-a-url'
        );

        $result = $this->handler->create($request);

        $this->assertFalse($result->success);
        $this->assertContains('Base URL is not a valid URL.', $result->errors);
    }
}
