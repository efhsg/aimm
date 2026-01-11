<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\CreateDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Yii;
use yii\db\Connection;

final class CreateDataSourceHandler implements CreateDataSourceInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly DataSourceQuery $query,
    ) {
    }

    public function create(CreateDataSourceRequest $request): SaveDataSourceResult
    {
        // Use model for validation only
        $model = new DataSource();
        $model->id = $request->id;
        $model->name = $request->name;
        $model->source_type = $request->sourceType;
        $model->base_url = $request->baseUrl;
        $model->notes = $request->notes;
        $model->is_active = 1;

        if (!$model->validate()) {
            return SaveDataSourceResult::failure(
                array_values(array_map(
                    fn (array $errors) => $errors[0],
                    $model->getErrors()
                ))
            );
        }

        // Use raw SQL to avoid Yii2 ActiveRecord bug with PHP 8.1+
        $this->db->createCommand()
            ->insert('data_source', [
                'id' => $request->id,
                'name' => $request->name,
                'source_type' => $request->sourceType,
                'base_url' => $request->baseUrl,
                'notes' => $request->notes,
                'is_active' => 1,
            ])
            ->execute();

        Yii::info("DataSource created: {$request->id} by {$request->actorUsername}", __METHOD__);

        $created = $this->query->findById($request->id);
        return SaveDataSourceResult::success($created);
    }
}
