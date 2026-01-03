<?php

declare(strict_types=1);

namespace app\controllers;

use app\filters\AdminAuthFilter;
use app\queries\CollectionRunRepository;
use Yii;
use yii\data\Pagination;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for listing and viewing collection runs.
 *
 * Provides read-only access to collection run history and status.
 * All actions require HTTP Basic Authentication.
 */
final class CollectionRunController extends Controller
{
    private const STATUS_OPTIONS = [
        'running' => 'Running',
        'complete' => 'Complete',
        'failed' => 'Failed',
    ];

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
     * List recent collection runs.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $statusParam = $request->get('status');
        $status = is_string($statusParam) && array_key_exists($statusParam, self::STATUS_OPTIONS)
            ? $statusParam
            : null;

        $search = $request->get('search');
        $search = is_string($search) ? trim($search) : null;
        $search = $search === '' ? null : $search;

        $totalCount = $this->runRepository->countRecent($status, $search);
        $pagination = new Pagination([
            'totalCount' => $totalCount,
            'pageSize' => 50,
        ]);

        $runs = $this->runRepository->listRecent($status, $search, $pagination->limit, $pagination->offset);

        return $this->render('index', [
            'runs' => $runs,
            'currentStatus' => $status,
            'currentSearch' => $search,
            'pagination' => $pagination,
            'totalCount' => $totalCount,
            'statusOptions' => self::STATUS_OPTIONS,
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
