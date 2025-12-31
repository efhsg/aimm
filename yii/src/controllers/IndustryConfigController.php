<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\industryconfig\CreateIndustryConfigRequest;
use app\dto\industryconfig\ToggleIndustryConfigRequest;
use app\dto\industryconfig\UpdateIndustryConfigRequest;
use app\filters\AdminAuthFilter;
use app\handlers\industryconfig\CreateIndustryConfigInterface;
use app\handlers\industryconfig\ToggleIndustryConfigInterface;
use app\handlers\industryconfig\UpdateIndustryConfigInterface;
use app\queries\IndustryConfigListQuery;
use app\validators\SchemaValidatorInterface;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing industry configurations.
 *
 * Provides CRUD operations for the admin UI.
 * All actions require HTTP Basic Authentication.
 */
final class IndustryConfigController extends Controller
{
    public $layout = 'main';

    private IndustryConfigListQuery $listQuery;
    private CreateIndustryConfigInterface $createHandler;
    private UpdateIndustryConfigInterface $updateHandler;
    private ToggleIndustryConfigInterface $toggleHandler;
    private SchemaValidatorInterface $schemaValidator;

    public function __construct(
        $id,
        $module,
        IndustryConfigListQuery $listQuery,
        CreateIndustryConfigInterface $createHandler,
        UpdateIndustryConfigInterface $updateHandler,
        ToggleIndustryConfigInterface $toggleHandler,
        SchemaValidatorInterface $schemaValidator,
        $config = []
    ) {
        $this->listQuery = $listQuery;
        $this->createHandler = $createHandler;
        $this->updateHandler = $updateHandler;
        $this->toggleHandler = $toggleHandler;
        $this->schemaValidator = $schemaValidator;

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
     * List all industry configs.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $isActive = $request->get('status');
        if ($isActive === 'active') {
            $isActive = true;
        } elseif ($isActive === 'inactive') {
            $isActive = false;
        } else {
            $isActive = null;
        }

        $search = $request->get('search');
        $orderBy = (string) $request->get('order', 'name');
        $orderDirection = (string) $request->get('dir', 'ASC');

        $list = $this->listQuery->list(
            $isActive,
            is_string($search) ? $search : null,
            $orderBy,
            $orderDirection
        );
        $counts = $this->listQuery->getCounts();

        return $this->render('index', [
            'configs' => $list->items,
            'total' => $list->total,
            'counts' => $counts,
            'currentStatus' => $request->get('status'),
            'currentSearch' => $search,
            'currentOrder' => $orderBy,
            'currentDir' => $orderDirection,
        ]);
    }

    /**
     * View a single industry config.
     */
    public function actionView(string $industry_id): string
    {
        $config = $this->listQuery->findByIndustryId($industry_id);

        if ($config === null) {
            throw new NotFoundHttpException('Industry config not found.');
        }

        $jsonValid = true;
        $jsonErrors = [];

        $result = $this->schemaValidator->validate(
            $config->configJson,
            'industry-config.schema.json'
        );

        if (!$result->isValid()) {
            $jsonValid = false;
            $jsonErrors = $result->getErrors();
        }

        return $this->render('view', [
            'config' => $config,
            'jsonValid' => $jsonValid,
            'jsonErrors' => $jsonErrors,
        ]);
    }

    /**
     * Create a new industry config.
     */
    public function actionCreate(): Response|string
    {
        $request = Yii::$app->request;

        if ($request->isPost) {
            $industryId = $request->post('industry_id', '');
            $configJson = $request->post('config_json', '');

            $result = $this->createHandler->create(new CreateIndustryConfigRequest(
                industryId: $industryId,
                configJson: $configJson,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                isActive: true,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Industry config created successfully.');
                return $this->redirect(['view', 'industry_id' => $result->config->industryId]);
            }

            return $this->render('create', [
                'industryId' => $industryId,
                'configJson' => $configJson,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('create', [
            'industryId' => '',
            'configJson' => $this->getConfigTemplate(),
            'errors' => [],
        ]);
    }

    /**
     * Update an existing industry config.
     */
    public function actionUpdate(string $industry_id): Response|string
    {
        $config = $this->listQuery->findByIndustryId($industry_id);

        if ($config === null) {
            throw new NotFoundHttpException('Industry config not found.');
        }

        $request = Yii::$app->request;

        if ($request->isPost) {
            $configJson = $request->post('config_json', '');

            $result = $this->updateHandler->update(new UpdateIndustryConfigRequest(
                industryId: $industry_id,
                configJson: $configJson,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Industry config updated successfully.');
                return $this->redirect(['view', 'industry_id' => $industry_id]);
            }

            return $this->render('update', [
                'config' => $config,
                'configJson' => $configJson,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('update', [
            'config' => $config,
            'configJson' => $config->configJson,
            'errors' => [],
        ]);
    }

    /**
     * Toggle active status (POST only).
     */
    public function actionToggle(string $industry_id): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $config = $this->listQuery->findByIndustryId($industry_id);

        if ($config === null) {
            throw new NotFoundHttpException('Industry config not found.');
        }

        $result = $this->toggleHandler->toggle(new ToggleIndustryConfigRequest(
            industryId: $industry_id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            $status = $result->config->isActive ? 'enabled' : 'disabled';
            Yii::$app->session->setFlash('success', "Industry config {$status} successfully.");
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        $returnUrl = $request->post('return_url', ['index']);
        return $this->redirect($returnUrl);
    }

    /**
     * Validate JSON (AJAX endpoint).
     */
    public function actionValidateJson(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;

        if (!$request->isPost) {
            return $this->asJson(['valid' => false, 'errors' => ['Method not allowed.']]);
        }

        $configJson = $request->post('config_json', '');
        $industryId = $request->post('industry_id');

        if ($configJson === '') {
            return $this->asJson(['valid' => false, 'errors' => ['Configuration JSON is required.']]);
        }

        $decoded = json_decode($configJson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->asJson([
                'valid' => false,
                'errors' => ['JSON syntax error: ' . json_last_error_msg()],
            ]);
        }

        $result = $this->schemaValidator->validate($configJson, 'industry-config.schema.json');

        if (!$result->isValid()) {
            return $this->asJson(['valid' => false, 'errors' => $result->getErrors()]);
        }

        if ($industryId !== null && is_object($decoded) && isset($decoded->id)) {
            if ($decoded->id !== $industryId) {
                return $this->asJson([
                    'valid' => false,
                    'errors' => [sprintf(
                        'Configuration "id" (%s) must match industry_id (%s).',
                        $decoded->id,
                        $industryId
                    )],
                ]);
            }
        }

        return $this->asJson(['valid' => true, 'errors' => []]);
    }

    private function getConfigTemplate(): string
    {
        return json_encode([
            'id' => '',
            'name' => '',
            'sector' => '',
            'companies' => [
                [
                    'ticker' => '',
                    'name' => '',
                    'listing_exchange' => '',
                    'listing_currency' => 'USD',
                    'reporting_currency' => 'USD',
                    'fy_end_month' => 12,
                ],
            ],
            'macro_requirements' => new \stdClass(),
            'data_requirements' => [
                'history_years' => 5,
                'quarters_to_fetch' => 4,
                'valuation_metrics' => [],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
