<?php

declare(strict_types=1);

namespace tests\unit\handlers\datasource;

use app\dto\datasource\DeleteDataSourceRequest;
use app\handlers\datasource\DeleteDataSourceHandler;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Codeception\Test\Unit;
use Yii;

final class DeleteDataSourceHandlerTest extends Unit
{
    private DeleteDataSourceHandler $handler;

    protected function _before(): void
    {
        // Clean up dependent tables if necessary
        // Assuming no dependent records exist for this test setup as we just created the source
        DataSource::deleteAll();
        $this->handler = new DeleteDataSourceHandler(
            Yii::$app->db,
            new DataSourceQuery(Yii::$app->db),
        );

        $model = new DataSource();
        $model->id = 'test_api';
        $model->name = 'Test API';
        $model->source_type = 'api';
        $model->save(false);
    }

    public function testDeleteSucceeds(): void
    {
        $request = new DeleteDataSourceRequest(
            id: 'test_api',
            actorUsername: 'tester'
        );

        $result = $this->handler->delete($request);

        $this->assertTrue($result->success);
        $this->assertNull(DataSource::findOne('test_api'));
    }

    public function testDeleteFailsIfNotFound(): void
    {
        $request = new DeleteDataSourceRequest(
            id: 'missing',
            actorUsername: 'tester'
        );

        $result = $this->handler->delete($request);

        $this->assertFalse($result->success);
        $this->assertContains('Data source not found.', $result->errors);
    }

    // Note: Testing IntegrityException requires setting up dependent records in another table
    // (e.g. annual_financial) which might be complicated in this unit test environment
    // without mocking DB behavior or having full fixtures.
    // We'll skip that for now as it relies on DB constraints.
}
