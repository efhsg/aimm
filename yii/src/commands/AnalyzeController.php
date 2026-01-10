<?php

declare(strict_types=1);

namespace app\commands;

use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\AnalyzeReportRequest;
use app\handlers\analysis\AnalyzeReportInterface;
use app\queries\AnalysisReportRepository;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\IndustryAnalysisQuery;
use app\queries\IndustryQuery;
use Throwable;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;
use yii\log\Logger;

/**
 * Analyze collected data and generate reports.
 */
final class AnalyzeController extends Controller
{
    private const LOG_CATEGORY = 'analysis';

    /**
     * Output file path for the report JSON.
     */
    public ?string $output = null;
    public bool $noSave = false;

    public function __construct(
        string $id,
        Module $module,
        private AnalyzeReportInterface $analyzer,
        private CollectionRunRepository $collectionRunRepository,
        private IndustryQuery $industryQuery,
        private CollectionPolicyQuery $collectionPolicyQuery,
        private AnalysisReportRepository $reportRepository,
        private IndustryAnalysisQuery $analysisQuery,
        private Logger $logger,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * @return list<string>
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['output', 'noSave']);
    }

    /**
     * Analyze an industry and generate a ranked report.
     *
     * @param string $slug The industry slug
     */
    public function actionIndustry(string $slug): int
    {
        $startedAt = microtime(true);

        // Find industry
        $group = $this->industryQuery->findBySlug($slug);
        if ($group === null) {
            $this->stderr("Industry not found: {$slug}\n");
            return ExitCode::DATAERR;
        }

        // Get latest successful collection run
        $collectionRun = $this->collectionRunRepository->getLatestSuccessful((int) $group['id']);
        if ($collectionRun === null) {
            $this->stderr("No successful collection found for: {$slug}\n");
            return ExitCode::DATAERR;
        }

        try {
            $policy = $this->resolvePolicy($group);

            // Build analysis context from dossier data
            $context = $this->analysisQuery->getForAnalysis((int) $group['id'], $slug, $policy);

            // Load thresholds from policy if available
            $thresholds = $this->loadThresholds($policy);

            // Run analysis for all companies
            $industryName = $group['name'] ?? $slug;
            $request = new AnalyzeReportRequest($context, $slug, $industryName, $thresholds);
            $result = $this->analyzer->handle($request);

            if (!$result->success) {
                $this->stderr("Analysis failed: {$result->errorMessage}\n");

                if ($result->gateResult !== null) {
                    foreach ($result->gateResult->errors as $error) {
                        $this->stderr("  - {$error->code}: {$error->message}\n");
                    }
                }

                return ExitCode::DATAERR;
            }

            if (!$this->noSave) {
                $this->reportRepository->saveRanked((int) $group['id'], $result->report);
            }

            // Output report
            $reportJson = Json::encode($result->report->toArray(), JSON_PRETTY_PRINT);

            if ($this->output !== null) {
                file_put_contents($this->output, $reportJson);
                $this->stdout("Report written to: {$this->output}\n");
            } else {
                $this->stdout($reportJson . "\n");
            }

            $duration = microtime(true) - $startedAt;
            $companyCount = count($result->report->companyAnalyses);
            $topRated = $companyCount > 0 ? $result->report->companyAnalyses[0] : null;
            $this->logger->log(
                [
                    'message' => 'Analysis completed',
                    'slug' => $slug,
                    'company_count' => $companyCount,
                    'top_rated' => $topRated?->ticker,
                    'top_rating' => $topRated?->rating->value,
                    'duration_seconds' => round($duration, 2),
                ],
                Logger::LEVEL_INFO,
                self::LOG_CATEGORY
            );

            return ExitCode::OK;
        } catch (Throwable $exception) {
            $this->logger->log(
                [
                    'message' => 'Analysis failed',
                    'slug' => $slug,
                    'error' => $exception->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                self::LOG_CATEGORY
            );
            $this->stderr('Analysis failed: ' . $exception->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Load analysis thresholds from policy if available.
     *
     * @param array<string, mixed>|null $policy
     */
    private function loadThresholds(?array $policy): AnalysisThresholds
    {
        if ($policy === null) {
            return new AnalysisThresholds();
        }

        $policyData = $this->decodeJson($policy['analysis_thresholds'] ?? null);
        if (!is_array($policyData)) {
            return new AnalysisThresholds();
        }

        return AnalysisThresholds::fromPolicy($policyData);
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>|null
     */
    private function resolvePolicy(array $group): ?array
    {
        $policyId = $group['policy_id'] ?? $group['collection_policy_id'] ?? null;
        if ($policyId === null) {
            return null;
        }

        return $this->collectionPolicyQuery->findById((int) $policyId);
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }
}
