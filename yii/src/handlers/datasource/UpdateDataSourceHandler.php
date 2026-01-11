<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\UpdateDataSourceRequest;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Yii;
use yii\db\Connection;

final class UpdateDataSourceHandler implements UpdateDataSourceInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly DataSourceQuery $query,
    ) {
    }

    public function update(UpdateDataSourceRequest $request): SaveDataSourceResult
    {
        $existing = $this->query->findById($request->id);

        if ($existing === null) {
            return SaveDataSourceResult::failure(['Data source not found.']);
        }

        // Use model for validation only (skip 'id' since it's an existing record)
        $model = new DataSource();
        $model->id = $request->id;
        $model->name = $request->name;
        $model->source_type = $request->sourceType;
        $model->base_url = $request->baseUrl;
        $model->notes = $request->notes;
        $model->is_active = $existing['is_active'];

        // Skip 'id' validation - it's an existing record, not a new one
        if (!$model->validate(['name', 'source_type', 'base_url', 'notes', 'is_active'])) {
            return SaveDataSourceResult::failure(
                array_values(array_map(
                    fn (array $errors) => $errors[0],
                    $model->getErrors()
                ))
            );
        }

        // Use raw SQL to avoid Yii2 ActiveRecord bug with PHP 8.1+
        $this->db->createCommand()
            ->update('data_source', [
                'name' => $request->name,
                'source_type' => $request->sourceType,
                'base_url' => $request->baseUrl,
                'notes' => $request->notes,
            ], ['id' => $request->id])
            ->execute();

        Yii::info("DataSource updated: {$request->id} by {$request->actorUsername}", __METHOD__);

        $updated = $this->query->findById($request->id);
        return SaveDataSourceResult::success($updated);
    }
}
