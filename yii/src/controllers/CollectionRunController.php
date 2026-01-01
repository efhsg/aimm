<?php

declare(strict_types=1);

namespace app\controllers;

use app\filters\AdminAuthFilter;
use app\queries\CollectionRunRepository;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for viewing collection run details.
 *
 * Provides read-only access to collection run history and status.
 * All actions require HTTP Basic Authentication.
 */
final class CollectionRunController extends Controller
{
    public $layout = 'main';

    public function __construct(
        $id,
        $module,
        private readonly CollectionRunRepository $runRepository,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'auth' => [
                'class' => AdminAuthFilter::class,
            ],
        ];
    }

    /**
     * View a collection run with errors and warnings.
     */
    public function actionView(int $id): string
    {
        $run = $this->runRepository->findById($id);

        if ($run === null) {
            throw new NotFoundHttpException('Collection run not found.');
        }

        $errors = $this->runRepository->getErrors($id);

        return $this->render('view', [
            'run' => $run,
            'errors' => array_filter($errors, fn ($e) => $e['severity'] === 'error'),
            'warnings' => array_filter($errors, fn ($e) => $e['severity'] === 'warning'),
        ]);
    }

    /**
     * JSON status endpoint for polling.
     */
    public function actionStatus(int $id): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = $this->runRepository->findById($id);

        if ($run === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['error' => 'Collection run not found.']);
        }

        return $this->asJson([
            'id' => (int) $run['id'],
            'status' => $run['status'],
            'started_at' => $run['started_at'],
            'completed_at' => $run['completed_at'],
            'companies_total' => (int) ($run['companies_total'] ?? 0),
            'companies_success' => (int) ($run['companies_success'] ?? 0),
            'companies_failed' => (int) ($run['companies_failed'] ?? 0),
            'gate_passed' => (bool) ($run['gate_passed'] ?? false),
            'error_count' => (int) ($run['error_count'] ?? 0),
            'warning_count' => (int) ($run['warning_count'] ?? 0),
            'duration_seconds' => (int) ($run['duration_seconds'] ?? 0),
        ]);
    }
}
