<?php

declare(strict_types=1);

namespace app\controllers;

use app\filters\AdminAuthFilter;
use app\queries\CollectionRunRepository;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
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
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
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
     * View a collection run with errors, warnings, and collected data.
     */
    public function actionView(int $id): string
    {
        $run = $this->runRepository->findById($id);

        if ($run === null) {
            throw new NotFoundHttpException('Collection run not found.');
        }

        $errors = $this->runRepository->getErrors($id);

        // Fetch available years/dates and latest data
        $availableFilters = $this->fetchAvailableFilters($run['industry_id']);
        $collectedData = $this->fetchCollectedData($run['industry_id']);

        return $this->render('view', [
            'run' => $run,
            'errors' => array_filter($errors, fn ($e) => $e['severity'] === 'error'),
            'warnings' => array_filter($errors, fn ($e) => $e['severity'] === 'warning'),
            'companies' => $collectedData['companies'],
            'annualFinancials' => $collectedData['annualFinancials'],
            'valuations' => $collectedData['valuations'],
            'macroIndicators' => $collectedData['macroIndicators'],
            'availableYears' => $availableFilters['years'],
            'availableValuationDates' => $availableFilters['valuationDates'],
            'availableMacroDates' => $availableFilters['macroDates'],
        ]);
    }

    /**
     * AJAX endpoint for filtered data.
     */
    public function actionData(int $id): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = $this->runRepository->findById($id);
        if ($run === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['error' => 'Collection run not found.']);
        }

        $request = Yii::$app->request;
        $type = $request->get('type');
        $filter = $request->get('filter');

        $data = match ($type) {
            'financials' => $this->fetchFinancialsByYear($run['industry_id'], (int) $filter),
            'valuations' => $this->fetchValuationsByDate($run['industry_id'], $filter),
            'macro' => $this->fetchMacroByDate($filter),
            default => [],
        };

        return $this->asJson(['data' => $data]);
    }

    /**
     * Fetch collected data for a peer group.
     *
     * @return array{companies: array, annualFinancials: array, valuations: array, macroIndicators: array}
     */
    private function fetchCollectedData(string $industrySlug): array
    {
        $peerGroup = $this->peerGroupQuery->findBySlug($industrySlug);
        if ($peerGroup === null) {
            return [
                'companies' => [],
                'annualFinancials' => [],
                'valuations' => [],
                'macroIndicators' => [],
            ];
        }

        $members = $this->memberQuery->findByGroup((int) $peerGroup['id']);
        $companyIds = array_column($members, 'company_id');

        if (empty($companyIds)) {
            return [
                'companies' => $members,
                'annualFinancials' => [],
                'valuations' => [],
                'macroIndicators' => [],
            ];
        }

        $db = Yii::$app->db;

        // Build named parameters for IN clause
        $params = [];
        $inPlaceholders = [];
        foreach ($companyIds as $i => $id) {
            $key = ":id{$i}";
            $params[$key] = $id;
            $inPlaceholders[] = $key;
        }
        $inClause = implode(',', $inPlaceholders);

        // Latest annual financials per company (most recent year)
        $annualFinancials = $db->createCommand(
            "SELECT af.*, c.ticker, c.name as company_name
             FROM annual_financial af
             JOIN company c ON af.company_id = c.id
             WHERE af.company_id IN ($inClause)
               AND af.is_current = 1
               AND af.fiscal_year = (
                   SELECT MAX(af2.fiscal_year)
                   FROM annual_financial af2
                   WHERE af2.company_id = af.company_id AND af2.is_current = 1
               )
             ORDER BY c.ticker"
        )->bindValues($params)->queryAll();

        // Latest valuation per company
        $valuations = $db->createCommand(
            "SELECT vs.*, c.ticker, c.name as company_name
             FROM valuation_snapshot vs
             JOIN company c ON vs.company_id = c.id
             WHERE vs.company_id IN ($inClause)
               AND vs.snapshot_date = (
                   SELECT MAX(vs2.snapshot_date)
                   FROM valuation_snapshot vs2
                   WHERE vs2.company_id = vs.company_id
               )
             ORDER BY c.ticker"
        )->bindValues($params)->queryAll();

        // Latest macro indicators (one per key)
        $macroIndicators = $db->createCommand(
            "SELECT m1.*
             FROM macro_indicator m1
             INNER JOIN (
                 SELECT indicator_key, MAX(indicator_date) as max_date
                 FROM macro_indicator
                 GROUP BY indicator_key
             ) m2 ON m1.indicator_key = m2.indicator_key AND m1.indicator_date = m2.max_date
             ORDER BY m1.indicator_key"
        )->queryAll();

        return [
            'companies' => $members,
            'annualFinancials' => $annualFinancials,
            'valuations' => $valuations,
            'macroIndicators' => $macroIndicators,
        ];
    }

    /**
     * Fetch available filter options (years, dates).
     *
     * @return array{years: list<int>, valuationDates: list<string>, macroDates: list<string>}
     */
    private function fetchAvailableFilters(string $industrySlug): array
    {
        $peerGroup = $this->peerGroupQuery->findBySlug($industrySlug);
        if ($peerGroup === null) {
            return ['years' => [], 'valuationDates' => [], 'macroDates' => []];
        }

        $members = $this->memberQuery->findByGroup((int) $peerGroup['id']);
        $companyIds = array_column($members, 'company_id');

        $db = Yii::$app->db;

        if (empty($companyIds)) {
            $years = [];
            $valuationDates = [];
        } else {
            $params = [];
            $inPlaceholders = [];
            foreach ($companyIds as $i => $id) {
                $key = ":id{$i}";
                $params[$key] = $id;
                $inPlaceholders[] = $key;
            }
            $inClause = implode(',', $inPlaceholders);

            $years = $db->createCommand(
                "SELECT DISTINCT fiscal_year FROM annual_financial
                 WHERE company_id IN ($inClause) AND is_current = 1
                 ORDER BY fiscal_year DESC"
            )->bindValues($params)->queryColumn();

            $valuationDates = $db->createCommand(
                "SELECT DISTINCT snapshot_date FROM valuation_snapshot
                 WHERE company_id IN ($inClause)
                 ORDER BY snapshot_date DESC"
            )->bindValues($params)->queryColumn();
        }

        $macroDates = $db->createCommand(
            "SELECT DISTINCT indicator_date FROM macro_indicator
             ORDER BY indicator_date DESC"
        )->queryColumn();

        return [
            'years' => array_map('intval', $years),
            'valuationDates' => $valuationDates,
            'macroDates' => $macroDates,
        ];
    }

    /**
     * Fetch annual financials for a specific year.
     */
    private function fetchFinancialsByYear(string $industrySlug, int $year): array
    {
        $peerGroup = $this->peerGroupQuery->findBySlug($industrySlug);
        if ($peerGroup === null) {
            return [];
        }

        $members = $this->memberQuery->findByGroup((int) $peerGroup['id']);
        $companyIds = array_column($members, 'company_id');

        if (empty($companyIds)) {
            return [];
        }

        $params = [':year' => $year];
        $inPlaceholders = [];
        foreach ($companyIds as $i => $id) {
            $key = ":id{$i}";
            $params[$key] = $id;
            $inPlaceholders[] = $key;
        }
        $inClause = implode(',', $inPlaceholders);

        return Yii::$app->db->createCommand(
            "SELECT af.*, c.ticker, c.name as company_name
             FROM annual_financial af
             JOIN company c ON af.company_id = c.id
             WHERE af.company_id IN ($inClause)
               AND af.is_current = 1
               AND af.fiscal_year = :year
             ORDER BY c.ticker"
        )->bindValues($params)->queryAll();
    }

    /**
     * Fetch valuations for a specific date.
     */
    private function fetchValuationsByDate(string $industrySlug, string $date): array
    {
        $peerGroup = $this->peerGroupQuery->findBySlug($industrySlug);
        if ($peerGroup === null) {
            return [];
        }

        $members = $this->memberQuery->findByGroup((int) $peerGroup['id']);
        $companyIds = array_column($members, 'company_id');

        if (empty($companyIds)) {
            return [];
        }

        $params = [':date' => $date];
        $inPlaceholders = [];
        foreach ($companyIds as $i => $id) {
            $key = ":id{$i}";
            $params[$key] = $id;
            $inPlaceholders[] = $key;
        }
        $inClause = implode(',', $inPlaceholders);

        return Yii::$app->db->createCommand(
            "SELECT vs.*, c.ticker, c.name as company_name
             FROM valuation_snapshot vs
             JOIN company c ON vs.company_id = c.id
             WHERE vs.company_id IN ($inClause)
               AND vs.snapshot_date = :date
             ORDER BY c.ticker"
        )->bindValues($params)->queryAll();
    }

    /**
     * Fetch macro indicators for a specific date.
     */
    private function fetchMacroByDate(string $date): array
    {
        return Yii::$app->db->createCommand(
            "SELECT * FROM macro_indicator
             WHERE indicator_date = :date
             ORDER BY indicator_key"
        )->bindValue(':date', $date)->queryAll();
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
