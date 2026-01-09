<?php

declare(strict_types=1);

namespace app\controllers;

use app\queries\CollectionRunRepository;
use app\queries\IndustryListQuery;
use Yii;
use yii\web\Controller;

/**
 * Dashboard controller for the homepage.
 *
 * Displays summary cards with system status and recent activity.
 */
final class DashboardController extends Controller
{
    public $layout = 'dashboard';

    public function __construct(
        $id,
        $module,
        private readonly IndustryListQuery $industryQuery,
        private readonly CollectionRunRepository $runRepository,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $industryCounts = $this->industryQuery->getCounts();
        $recentRuns = $this->runRepository->listRecent(limit: 5);
        $policyCount = $this->getPolicyCount();
        $runStats = $this->getRunStats();

        return $this->render('index', [
            'industryCounts' => $industryCounts,
            'recentRuns' => $recentRuns,
            'policyCount' => $policyCount,
            'runStats' => $runStats,
        ]);
    }

    private function getPolicyCount(): int
    {
        return (int) Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM {{%collection_policy}}'
        )->queryScalar();
    }

    /**
     * @return array{total: int, complete: int, failed: int, running: int}
     */
    private function getRunStats(): array
    {
        $db = Yii::$app->db;

        $total = (int) $db->createCommand(
            'SELECT COUNT(*) FROM {{%collection_run}}'
        )->queryScalar();

        $complete = (int) $db->createCommand(
            'SELECT COUNT(*) FROM {{%collection_run}} WHERE status = :status'
        )->bindValue(':status', 'complete')->queryScalar();

        $failed = (int) $db->createCommand(
            'SELECT COUNT(*) FROM {{%collection_run}} WHERE status = :status'
        )->bindValue(':status', 'failed')->queryScalar();

        $running = (int) $db->createCommand(
            'SELECT COUNT(*) FROM {{%collection_run}} WHERE status = :status'
        )->bindValue(':status', 'running')->queryScalar();

        return [
            'total' => $total,
            'complete' => $complete,
            'failed' => $failed,
            'running' => $running,
        ];
    }
}
