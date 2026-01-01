<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\collectionpolicy\CreateCollectionPolicyRequest;
use app\dto\collectionpolicy\DeleteCollectionPolicyRequest;
use app\dto\collectionpolicy\SetDefaultPolicyRequest;
use app\dto\collectionpolicy\UpdateCollectionPolicyRequest;
use app\filters\AdminAuthFilter;
use app\handlers\collectionpolicy\CreateCollectionPolicyInterface;
use app\handlers\collectionpolicy\DeleteCollectionPolicyInterface;
use app\handlers\collectionpolicy\SetDefaultPolicyInterface;
use app\handlers\collectionpolicy\UpdateCollectionPolicyInterface;
use app\queries\CollectionPolicyQuery;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing collection policies.
 *
 * Provides CRUD operations for the admin UI.
 * All actions require HTTP Basic Authentication.
 */
final class CollectionPolicyController extends Controller
{
    public $layout = 'main';

    public function __construct(
        $id,
        $module,
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly CreateCollectionPolicyInterface $createHandler,
        private readonly UpdateCollectionPolicyInterface $updateHandler,
        private readonly DeleteCollectionPolicyInterface $deleteHandler,
        private readonly SetDefaultPolicyInterface $setDefaultHandler,
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
        $policies = $this->policyQuery->findAll();

        return $this->render('index', [
            'policies' => $policies,
        ]);
    }

    public function actionView(string $slug): string
    {
        $policy = $this->policyQuery->findBySlug($slug);

        if ($policy === null) {
            throw new NotFoundHttpException('Collection policy not found.');
        }

        return $this->render('view', [
            'policy' => $policy,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $request = Yii::$app->request;

        if ($request->isPost) {
            $slug = trim((string) $request->post('slug', ''));
            $name = trim((string) $request->post('name', ''));
            $description = trim((string) $request->post('description', ''));

            // Auto-generate slug from name if not provided
            if ($slug === '' && $name !== '') {
                $slug = $this->generateSlug($name);
            }

            $result = $this->createHandler->create(new CreateCollectionPolicyRequest(
                slug: $slug,
                name: $name,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                description: $description !== '' ? $description : null,
                historyYears: (int) $request->post('history_years', 5),
                quartersToFetch: (int) $request->post('quarters_to_fetch', 8),
                valuationMetricsJson: $this->normalizeJson($request->post('valuation_metrics')),
                annualFinancialMetricsJson: $this->normalizeJson($request->post('annual_financial_metrics')),
                quarterlyFinancialMetricsJson: $this->normalizeJson($request->post('quarterly_financial_metrics')),
                operationalMetricsJson: $this->normalizeJson($request->post('operational_metrics')),
                commodityBenchmark: $this->normalizeString($request->post('commodity_benchmark')),
                marginProxy: $this->normalizeString($request->post('margin_proxy')),
                sectorIndex: $this->normalizeString($request->post('sector_index')),
                requiredIndicatorsJson: $this->normalizeJson($request->post('required_indicators')),
                optionalIndicatorsJson: $this->normalizeJson($request->post('optional_indicators')),
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Collection policy created successfully.');
                return $this->redirect(['view', 'slug' => $result->policy['slug']]);
            }

            return $this->render('create', [
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'historyYears' => (int) $request->post('history_years', 5),
                'quartersToFetch' => (int) $request->post('quarters_to_fetch', 8),
                'valuationMetrics' => $request->post('valuation_metrics', ''),
                'annualFinancialMetrics' => $request->post('annual_financial_metrics', ''),
                'quarterlyFinancialMetrics' => $request->post('quarterly_financial_metrics', ''),
                'operationalMetrics' => $request->post('operational_metrics', ''),
                'commodityBenchmark' => $request->post('commodity_benchmark', ''),
                'marginProxy' => $request->post('margin_proxy', ''),
                'sectorIndex' => $request->post('sector_index', ''),
                'requiredIndicators' => $request->post('required_indicators', ''),
                'optionalIndicators' => $request->post('optional_indicators', ''),
                'errors' => $result->errors,
            ]);
        }

        return $this->render('create', [
            'slug' => '',
            'name' => '',
            'description' => '',
            'historyYears' => 5,
            'quartersToFetch' => 8,
            'valuationMetrics' => $this->getMetricsTemplate(),
            'annualFinancialMetrics' => '',
            'quarterlyFinancialMetrics' => '',
            'operationalMetrics' => '',
            'commodityBenchmark' => '',
            'marginProxy' => '',
            'sectorIndex' => '',
            'requiredIndicators' => '',
            'optionalIndicators' => '',
            'errors' => [],
        ]);
    }

    public function actionUpdate(string $slug): Response|string
    {
        $policy = $this->policyQuery->findBySlug($slug);

        if ($policy === null) {
            throw new NotFoundHttpException('Collection policy not found.');
        }

        $request = Yii::$app->request;

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $description = trim((string) $request->post('description', ''));

            $result = $this->updateHandler->update(new UpdateCollectionPolicyRequest(
                id: (int) $policy['id'],
                name: $name,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                description: $description !== '' ? $description : null,
                historyYears: (int) $request->post('history_years', 5),
                quartersToFetch: (int) $request->post('quarters_to_fetch', 8),
                valuationMetricsJson: $this->normalizeJson($request->post('valuation_metrics')),
                annualFinancialMetricsJson: $this->normalizeJson($request->post('annual_financial_metrics')),
                quarterlyFinancialMetricsJson: $this->normalizeJson($request->post('quarterly_financial_metrics')),
                operationalMetricsJson: $this->normalizeJson($request->post('operational_metrics')),
                commodityBenchmark: $this->normalizeString($request->post('commodity_benchmark')),
                marginProxy: $this->normalizeString($request->post('margin_proxy')),
                sectorIndex: $this->normalizeString($request->post('sector_index')),
                requiredIndicatorsJson: $this->normalizeJson($request->post('required_indicators')),
                optionalIndicatorsJson: $this->normalizeJson($request->post('optional_indicators')),
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Collection policy updated successfully.');
                return $this->redirect(['view', 'slug' => $slug]);
            }

            return $this->render('update', [
                'policy' => $policy,
                'name' => $name,
                'description' => $description,
                'historyYears' => (int) $request->post('history_years', 5),
                'quartersToFetch' => (int) $request->post('quarters_to_fetch', 8),
                'valuationMetrics' => $request->post('valuation_metrics', ''),
                'annualFinancialMetrics' => $request->post('annual_financial_metrics', ''),
                'quarterlyFinancialMetrics' => $request->post('quarterly_financial_metrics', ''),
                'operationalMetrics' => $request->post('operational_metrics', ''),
                'commodityBenchmark' => $request->post('commodity_benchmark', ''),
                'marginProxy' => $request->post('margin_proxy', ''),
                'sectorIndex' => $request->post('sector_index', ''),
                'requiredIndicators' => $request->post('required_indicators', ''),
                'optionalIndicators' => $request->post('optional_indicators', ''),
                'errors' => $result->errors,
            ]);
        }

        return $this->render('update', [
            'policy' => $policy,
            'name' => $policy['name'],
            'description' => $policy['description'] ?? '',
            'historyYears' => (int) ($policy['history_years'] ?? 5),
            'quartersToFetch' => (int) ($policy['quarters_to_fetch'] ?? 8),
            'valuationMetrics' => $this->formatJson($policy['valuation_metrics']),
            'annualFinancialMetrics' => $this->formatJson($policy['annual_financial_metrics']),
            'quarterlyFinancialMetrics' => $this->formatJson($policy['quarterly_financial_metrics']),
            'operationalMetrics' => $this->formatJson($policy['operational_metrics']),
            'commodityBenchmark' => $policy['commodity_benchmark'] ?? '',
            'marginProxy' => $policy['margin_proxy'] ?? '',
            'sectorIndex' => $policy['sector_index'] ?? '',
            'requiredIndicators' => $this->formatJson($policy['required_indicators']),
            'optionalIndicators' => $this->formatJson($policy['optional_indicators']),
            'errors' => [],
        ]);
    }

    public function actionDelete(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $policy = $this->policyQuery->findBySlug($slug);

        if ($policy === null) {
            throw new NotFoundHttpException('Collection policy not found.');
        }

        $result = $this->deleteHandler->delete(new DeleteCollectionPolicyRequest(
            id: (int) $policy['id'],
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash('success', 'Collection policy deleted successfully.');
            return $this->redirect(['index']);
        }

        Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        return $this->redirect(['view', 'slug' => $slug]);
    }

    public function actionSetDefault(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $policy = $this->policyQuery->findBySlug($slug);

        if ($policy === null) {
            throw new NotFoundHttpException('Collection policy not found.');
        }

        $sector = trim((string) $request->post('sector', ''));
        $clear = (bool) $request->post('clear', false);

        $result = $this->setDefaultHandler->setDefault(new SetDefaultPolicyRequest(
            id: (int) $policy['id'],
            sector: $sector,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
            clear: $clear,
        ));

        if ($result->success) {
            $message = $clear
                ? "Cleared as default for sector '{$sector}'."
                : "Set as default for sector '{$sector}'.";
            Yii::$app->session->setFlash('success', $message);
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        return $this->redirect(['view', 'slug' => $slug]);
    }

    public function actionExport(string $slug): Response
    {
        $policy = $this->policyQuery->findBySlug($slug);

        if ($policy === null) {
            throw new NotFoundHttpException('Collection policy not found.');
        }

        // Remove internal fields
        unset($policy['id'], $policy['created_by'], $policy['created_at'], $policy['updated_at']);

        $json = json_encode($policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return Yii::$app->response->sendContentAsFile(
            $json,
            $slug . '.json',
            ['mimeType' => 'application/json']
        );
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null || !is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeJson(mixed $value): ?string
    {
        if ($value === null || !is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function formatJson(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            return $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return '';
    }

    private function getMetricsTemplate(): string
    {
        return json_encode([
            [
                'key' => 'pe_ratio',
                'unit' => 'ratio',
                'required' => true,
                'required_scope' => 'all',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
