<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\datasource\CreateDataSourceRequest;
use app\dto\datasource\DeleteDataSourceRequest;
use app\dto\datasource\ToggleDataSourceRequest;
use app\dto\datasource\UpdateDataSourceRequest;
use app\filters\AdminAuthFilter;
use app\handlers\datasource\CreateDataSourceInterface;
use app\handlers\datasource\DeleteDataSourceInterface;
use app\handlers\datasource\ToggleDataSourceInterface;
use app\handlers\datasource\UpdateDataSourceInterface;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing data sources.
 *
 * Provides CRUD operations for the admin UI.
 * All actions require HTTP Basic Authentication.
 */
final class DataSourceController extends Controller
{
    public $layout = 'main';

    public function __construct(
        $id,
        $module,
        private readonly DataSourceQuery $query,
        private readonly CreateDataSourceInterface $createHandler,
        private readonly UpdateDataSourceInterface $updateHandler,
        private readonly ToggleDataSourceInterface $toggleHandler,
        private readonly DeleteDataSourceInterface $deleteHandler,
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

    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $status = $request->get('status');
        $type = $request->get('type');
        $search = $request->get('search');

        $dataSources = $this->query->list($status, $type, $search);
        $counts = $this->query->getCounts();

        return $this->render('index', [
            'dataSources' => $dataSources,
            'counts' => $counts,
            'currentStatus' => $status,
            'currentType' => $type,
            'search' => $search ?? '',
        ]);
    }

    public function actionView(string $id): string
    {
        $dataSource = $this->query->findById($id);

        if ($dataSource === null) {
            throw new NotFoundHttpException('Data source not found.');
        }

        $usingPolicies = $this->query->findPoliciesUsingSource($id);

        return $this->render('view', [
            'dataSource' => $dataSource,
            'usingPolicies' => $usingPolicies,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $request = Yii::$app->request;

        if ($request->isPost) {
            $id = trim((string) $request->post('id', ''));
            $name = trim((string) $request->post('name', ''));
            $sourceType = trim((string) $request->post('source_type', ''));
            $baseUrl = trim((string) $request->post('base_url', ''));
            $notes = trim((string) $request->post('notes', ''));

            $result = $this->createHandler->create(new CreateDataSourceRequest(
                id: $id,
                name: $name,
                sourceType: $sourceType,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                baseUrl: $baseUrl !== '' ? $baseUrl : null,
                notes: $notes !== '' ? $notes : null,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Data source created successfully.');
                return $this->redirect(['view', 'id' => $result->dataSource['id']]);
            }

            return $this->render('create', [
                'id' => $id,
                'name' => $name,
                'sourceType' => $sourceType,
                'baseUrl' => $baseUrl,
                'notes' => $notes,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('create', [
            'id' => '',
            'name' => '',
            'sourceType' => DataSource::SOURCE_TYPE_API,
            'baseUrl' => '',
            'notes' => '',
            'errors' => [],
        ]);
    }

    public function actionUpdate(string $id): Response|string
    {
        $dataSource = $this->query->findById($id);

        if ($dataSource === null) {
            throw new NotFoundHttpException('Data source not found.');
        }

        $request = Yii::$app->request;

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $sourceType = trim((string) $request->post('source_type', ''));
            $baseUrl = trim((string) $request->post('base_url', ''));
            $notes = trim((string) $request->post('notes', ''));

            $result = $this->updateHandler->update(new UpdateDataSourceRequest(
                id: $id,
                name: $name,
                sourceType: $sourceType,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                baseUrl: $baseUrl !== '' ? $baseUrl : null,
                notes: $notes !== '' ? $notes : null,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Data source updated successfully.');
                return $this->redirect(['view', 'id' => $id]);
            }

            return $this->render('update', [
                'id' => $id,
                'name' => $name,
                'sourceType' => $sourceType,
                'baseUrl' => $baseUrl,
                'notes' => $notes,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('update', [
            'id' => $dataSource['id'],
            'name' => $dataSource['name'],
            'sourceType' => $dataSource['source_type'],
            'baseUrl' => $dataSource['base_url'] ?? '',
            'notes' => $dataSource['notes'] ?? '',
            'errors' => [],
        ]);
    }

    public function actionToggle(string $id): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        // Verify data source exists before attempting toggle
        $dataSource = $this->query->findById($id);
        if ($dataSource === null) {
            Yii::$app->session->setFlash('error', 'Data source not found.');
            return $this->redirect(['index']);
        }

        $result = $this->toggleHandler->toggle(new ToggleDataSourceRequest(
            id: $id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success && $result->dataSource !== null) {
            $status = $result->dataSource['is_active'] ? 'activated' : 'deactivated';
            Yii::$app->session->setFlash('success', "Data source {$status} successfully.");
        } else {
            Yii::$app->session->setFlash('error', $result->errors[0] ?? 'Failed to toggle status.');
        }

        $returnUrl = $request->post('return_url', ['index']);
        return $this->redirect($returnUrl);
    }

    public function actionDelete(string $id): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        // Verify data source exists before attempting delete
        $dataSource = $this->query->findById($id);
        if ($dataSource === null) {
            Yii::$app->session->setFlash('error', 'Data source not found.');
            return $this->redirect(['index']);
        }

        $result = $this->deleteHandler->delete(new DeleteDataSourceRequest(
            id: $id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash('success', 'Data source deleted successfully.');
        } else {
            Yii::$app->session->setFlash('error', $result->errors[0] ?? 'Failed to delete data source.');
        }

        return $this->redirect(['index']);
    }
}
