<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\DeleteDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;
use app\queries\DataSourceQuery;
use Yii;
use yii\db\Connection;
use yii\db\IntegrityException;

final class DeleteDataSourceHandler implements DeleteDataSourceInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly DataSourceQuery $query,
    ) {
    }

    public function delete(DeleteDataSourceRequest $request): SaveDataSourceResult
    {
        $existing = $this->query->findById($request->id);

        if ($existing === null) {
            return SaveDataSourceResult::failure(['Data source not found.']);
        }

        // Check if used by any collection policies
        $usingPolicies = $this->query->findPoliciesUsingSource($request->id);
        if (!empty($usingPolicies)) {
            $policyNames = array_map(fn ($p) => $p['name'], $usingPolicies);
            Yii::warning("Cannot delete DataSource {$request->id}: used by policies", __METHOD__);
            return SaveDataSourceResult::failure([
                'Cannot delete this data source because it is used by collection policies: ' .
                implode(', ', $policyNames) . '. Remove it from the policies first.',
            ]);
        }

        try {
            // Use raw SQL to avoid Yii2 ActiveRecord bug with PHP 8.1+
            $rows = $this->db->createCommand()
                ->delete('data_source', ['id' => $request->id])
                ->execute();

            if ($rows === 0) {
                return SaveDataSourceResult::failure(['Failed to delete data source.']);
            }
        } catch (IntegrityException) {
            Yii::warning("Cannot delete DataSource {$request->id}: has dependent records", __METHOD__);
            return SaveDataSourceResult::failure([
                'Cannot delete this data source because it has associated data records. ' .
                'Deactivate it instead.',
            ]);
        }

        Yii::info("DataSource deleted: {$request->id} by {$request->actorUsername}", __METHOD__);

        return SaveDataSourceResult::success(['id' => $request->id]);
    }
}
