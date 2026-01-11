<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\ToggleDataSourceRequest;
use app\queries\DataSourceQuery;
use Yii;
use yii\db\Connection;

final class ToggleDataSourceHandler implements ToggleDataSourceInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly DataSourceQuery $query,
    ) {
    }

    public function toggle(ToggleDataSourceRequest $request): SaveDataSourceResult
    {
        $dataSource = $this->query->findById($request->id);

        if ($dataSource === null) {
            return SaveDataSourceResult::failure(['Data source not found.']);
        }

        $newStatus = $dataSource['is_active'] ? 0 : 1;

        $rows = $this->db->createCommand()
            ->update('data_source', ['is_active' => $newStatus], ['id' => $request->id])
            ->execute();

        if ($rows === 0) {
            Yii::error("Failed to toggle DataSource: {$request->id}", __METHOD__);
            return SaveDataSourceResult::failure(['Failed to toggle data source status.']);
        }

        $status = $newStatus ? 'activated' : 'deactivated';
        Yii::info("DataSource {$status}: {$request->id} by {$request->actorUsername}", __METHOD__);

        // Fetch updated record
        $updated = $this->query->findById($request->id);
        return SaveDataSourceResult::success($updated);
    }
}
