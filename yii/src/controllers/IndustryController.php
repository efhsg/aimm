<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\AnalyzeReportRequest;
use app\dto\industry\AddMembersRequest;
use app\dto\industry\CollectIndustryRequest;
use app\dto\industry\CreateIndustryRequest;
use app\dto\industry\RemoveMemberRequest;
use app\dto\industry\ToggleIndustryRequest;
use app\dto\industry\UpdateIndustryRequest;
use app\filters\AdminAuthFilter;
use app\handlers\analysis\AnalyzeReportInterface;
use app\handlers\industry\AddMembersInterface;
use app\handlers\industry\CollectIndustryInterface;
use app\handlers\industry\CreateIndustryInterface;
use app\handlers\industry\RemoveMemberInterface;
use app\handlers\industry\ToggleIndustryInterface;
use app\handlers\industry\UpdateIndustryInterface;
use app\queries\AnalysisReportRepository;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\CompanyQuery;
use app\queries\IndustryListQuery;
use app\queries\IndustryMemberQuery;
use app\queries\SectorQuery;
use app\transformers\DossierToDataPackTransformer;
use Yii;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing industries.
 *
 * Provides CRUD operations for the admin UI.
 * All actions require HTTP Basic Authentication.
 */
final class IndustryController extends Controller
{
    public $layout = 'main';

    public function __construct(
        $id,
        $module,
        private readonly IndustryListQuery $listQuery,
        private readonly SectorQuery $sectorQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly IndustryMemberQuery $memberQuery,
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly CollectionRunRepository $runRepository,
        private readonly AnalysisReportRepository $reportRepository,
        private readonly CreateIndustryInterface $createHandler,
        private readonly UpdateIndustryInterface $updateHandler,
        private readonly ToggleIndustryInterface $toggleHandler,
        private readonly AddMembersInterface $addMembersHandler,
        private readonly RemoveMemberInterface $removeMemberHandler,
        private readonly CollectIndustryInterface $collectHandler,
        private readonly AnalyzeReportInterface $analyzeHandler,
        private readonly DossierToDataPackTransformer $dossierTransformer,
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
     * List all industries.
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $sectorId = $request->get('sector');
        $sectorId = is_numeric($sectorId) ? (int) $sectorId : null;

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
            $sectorId,
            $isActive,
            is_string($search) ? $search : null,
            $orderBy,
            $orderDirection
        );

        return $this->render('index', [
            'industries' => $list->industries,
            'counts' => $list->counts,
            'sectors' => $this->sectorQuery->findAll(),
            'currentSectorId' => $sectorId,
            'currentStatus' => $request->get('status'),
            'currentSearch' => $search,
            'currentOrder' => $orderBy,
            'currentDir' => $orderDirection,
        ]);
    }

    /**
     * View a single industry with companies.
     */
    public function actionView(string $slug): string
    {
        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $companies = $this->companyQuery->findByIndustry($industry->id);
        $runs = $this->runRepository->listByIndustry($industry->id);

        return $this->render('view', [
            'industry' => $industry,
            'companies' => $companies,
            'runs' => $runs,
        ]);
    }

    /**
     * Create a new industry.
     */
    public function actionCreate(): Response|string
    {
        $request = Yii::$app->request;
        $policies = $this->policyQuery->findAll();
        $sectors = $this->sectorQuery->findAll();

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $slug = trim((string) $request->post('slug', ''));
            $sectorId = $request->post('sector_id');
            $description = trim((string) $request->post('description', ''));
            $policyId = $request->post('policy_id');

            // Auto-generate slug from name if not provided
            if ($slug === '' && $name !== '') {
                $slug = $this->generateSlug($name);
            }

            $result = $this->createHandler->create(new CreateIndustryRequest(
                name: $name,
                slug: $slug,
                sectorId: $sectorId !== null && $sectorId !== '' ? (int) $sectorId : 0,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                description: $description !== '' ? $description : null,
                policyId: $policyId !== null && $policyId !== '' ? (int) $policyId : null,
                isActive: true,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Industry created successfully.');
                return $this->redirect(['view', 'slug' => $result->industry->slug]);
            }

            return $this->render('create', [
                'name' => $name,
                'slug' => $slug,
                'sectorId' => $sectorId,
                'description' => $description,
                'policyId' => $policyId,
                'policies' => $policies,
                'sectors' => $sectors,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('create', [
            'name' => '',
            'slug' => '',
            'sectorId' => null,
            'description' => '',
            'policyId' => null,
            'policies' => $policies,
            'sectors' => $sectors,
            'errors' => [],
        ]);
    }

    /**
     * Update an existing industry.
     */
    public function actionUpdate(string $slug): Response|string
    {
        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $request = Yii::$app->request;
        $policies = $this->policyQuery->findAll();

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $description = trim((string) $request->post('description', ''));
            $policyId = $request->post('policy_id');

            $result = $this->updateHandler->update(new UpdateIndustryRequest(
                id: $industry->id,
                name: $name,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                description: $description !== '' ? $description : null,
                policyId: $policyId !== null && $policyId !== '' ? (int) $policyId : null,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Industry updated successfully.');
                return $this->redirect(['view', 'slug' => $slug]);
            }

            return $this->render('update', [
                'industry' => $industry,
                'name' => $name,
                'description' => $description,
                'policyId' => $policyId,
                'policies' => $policies,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('update', [
            'industry' => $industry,
            'name' => $industry->name,
            'description' => $industry->description ?? '',
            'policyId' => $industry->policyId,
            'policies' => $policies,
            'errors' => [],
        ]);
    }

    /**
     * Toggle active status (POST only).
     */
    public function actionToggle(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $result = $this->toggleHandler->toggle(new ToggleIndustryRequest(
            id: $industry->id,
            isActive: !$industry->isActive,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            $status = $result->industry->isActive ? 'activated' : 'deactivated';
            Yii::$app->session->setFlash('success', "Industry {$status} successfully.");
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        $returnUrl = $request->post('return_url', ['index']);
        return $this->redirect($returnUrl);
    }

    /**
     * Add members to an industry (POST only).
     */
    public function actionAddMembers(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $tickersInput = trim((string) $request->post('tickers', ''));
        $tickers = $this->parseTickers($tickersInput);

        if (empty($tickers)) {
            Yii::$app->session->setFlash('error', 'No valid tickers provided.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        $result = $this->addMembersHandler->add(new AddMembersRequest(
            industryId: $industry->id,
            tickers: $tickers,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            $message = count($result->added) . ' member(s) added.';
            if (!empty($result->skipped)) {
                $message .= ' ' . count($result->skipped) . ' already existed.';
            }
            Yii::$app->session->setFlash('success', $message);
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Remove a member from an industry (POST only).
     */
    public function actionRemoveMember(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $companyId = (int) $request->post('company_id', 0);

        if ($companyId <= 0) {
            Yii::$app->session->setFlash('error', 'Invalid company ID.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        $result = $this->removeMemberHandler->remove(new RemoveMemberRequest(
            industryId: $industry->id,
            companyId: $companyId,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash('success', 'Member removed successfully.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        }

        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Trigger data collection for an industry (POST only).
     */
    public function actionCollect(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $result = $this->collectHandler->collect(new CollectIndustryRequest(
            industryId: $industry->id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash(
                'success',
                'Collection started. Run ID: ' . $result->runId
            );

            // Redirect to collection run view
            return $this->redirect(['collection-run/view', 'id' => $result->runId]);
        }

        Yii::$app->session->setFlash('error', implode(' ', $result->errors));
        return $this->redirect(['view', 'slug' => $slug]);
    }

    /**
     * Run analysis for all companies in an industry (POST only).
     */
    public function actionAnalyze(string $slug): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        // Build datapack from dossier data
        $dataPack = $this->dossierTransformer->transform($industry->id, $industry->slug);

        // Check if we have any company data
        if (empty($dataPack->companies)) {
            Yii::$app->session->setFlash('error', 'No company data found. Run collection first.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        // Check minimum companies
        if (count($dataPack->companies) < 2) {
            Yii::$app->session->setFlash('error', 'At least 2 companies required for analysis.');
            return $this->redirect(['view', 'slug' => $slug]);
        }

        // Load thresholds from policy if available
        $thresholds = $this->loadAnalysisThresholds($industry->policyId);

        // Run analysis
        $analysisRequest = new AnalyzeReportRequest(
            $dataPack,
            $industry->slug,
            $industry->name,
            $thresholds
        );
        $result = $this->analyzeHandler->handle($analysisRequest);

        if (!$result->success) {
            $message = 'Analysis failed: ' . $result->errorMessage;
            if ($result->gateResult !== null) {
                foreach ($result->gateResult->errors as $error) {
                    $message .= " [{$error->code}]";
                }
            }
            Yii::$app->session->setFlash('error', $message);
            return $this->redirect(['view', 'slug' => $slug]);
        }

        // Save report
        $this->reportRepository->saveRanked($industry->id, $result->report);

        $topRated = $result->report->companyAnalyses[0] ?? null;
        $message = 'Analysis complete.';
        if ($topRated !== null) {
            $message .= " Top rated: {$topRated->ticker} ({$topRated->rating->value})";
        }

        Yii::$app->session->setFlash('success', $message);

        return $this->redirect(['ranking', 'slug' => $slug]);
    }

    /**
     * View the latest ranked analysis report for an industry.
     */
    public function actionRanking(string $slug): string
    {
        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $reportRow = $this->reportRepository->getLatestRanking($industry->id);

        if ($reportRow === null) {
            throw new NotFoundHttpException('No ranking report found. Run analysis first.');
        }

        $reportData = $this->reportRepository->decodeReport($reportRow);

        return $this->render('ranking', [
            'industry' => $industry,
            'reportRow' => $reportRow,
            'report' => $reportData,
        ]);
    }

    /**
     * View a specific analysis report.
     */
    public function actionReport(string $slug, int $id): string
    {
        $industry = $this->listQuery->findBySlug($slug);

        if ($industry === null) {
            throw new NotFoundHttpException('Industry not found.');
        }

        $reportRow = $this->reportRepository->findById($id);

        if ($reportRow === null || (int) $reportRow['industry_id'] !== $industry->id) {
            throw new NotFoundHttpException('Report not found.');
        }

        $reportData = $this->reportRepository->decodeReport($reportRow);

        return $this->render('report', [
            'industry' => $industry,
            'reportRow' => $reportRow,
            'report' => $reportData,
        ]);
    }

    /**
     * Load analysis thresholds from policy.
     */
    private function loadAnalysisThresholds(?int $policyId): AnalysisThresholds
    {
        if ($policyId === null) {
            return new AnalysisThresholds();
        }

        $thresholdsJson = $this->policyQuery->findAnalysisThresholds($policyId);
        if ($thresholdsJson === null) {
            return new AnalysisThresholds();
        }

        $policyData = Json::decode($thresholdsJson);

        return AnalysisThresholds::fromPolicy($policyData);
    }

    /**
     * Generate a URL-safe slug from a name.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Parse ticker input into an array of normalized tickers.
     *
     * @return string[]
     */
    private function parseTickers(string $input): array
    {
        // Split by newlines or commas
        $parts = preg_split('/[\n,]+/', $input);
        $tickers = [];

        foreach ($parts as $part) {
            $ticker = strtoupper(trim($part));
            // Only allow alphanumeric and period (for tickers like BRK.B)
            $ticker = preg_replace('/[^A-Z0-9.]/', '', $ticker);
            if ($ticker !== '') {
                $tickers[] = $ticker;
            }
        }

        return array_unique($tickers);
    }
}
