<?php

declare(strict_types=1);

namespace app\commands;

use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\AnalyzeReportRequest;
use app\factories\IndustryDataPackFactory;
use app\handlers\analysis\AnalyzeReportInterface;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\PeerGroupQuery;
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
     * Focal company ticker for analysis.
     */
    public ?string $focal = null;

    /**
     * Output file path for the report JSON.
     */
    public ?string $output = null;

    public function __construct(
        string $id,
        Module $module,
        private AnalyzeReportInterface $analyzer,
        private CollectionRunRepository $collectionRunRepository,
        private PeerGroupQuery $peerGroupQuery,
        private CollectionPolicyQuery $collectionPolicyQuery,
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
        return array_merge(parent::options($actionID), ['focal', 'output']);
    }

    /**
     * Analyze a peer group and generate a report.
     *
     * @param string $slug The peer group slug
     */
    public function actionPeerGroup(string $slug): int
    {
        $startedAt = microtime(true);

        // Validate focal ticker is provided
        if ($this->focal === null || $this->focal === '') {
            $this->stderr("Error: --focal=TICKER is required\n");
            return ExitCode::USAGE;
        }

        $focalTicker = strtoupper(trim($this->focal));

        // Find peer group
        $group = $this->peerGroupQuery->findBySlug($slug);
        if ($group === null) {
            $this->stderr("Peer group not found: {$slug}\n");
            return ExitCode::DATAERR;
        }

        // Get latest successful datapack
        $collectionRun = $this->collectionRunRepository->getLatestSuccessful($slug);
        if ($collectionRun === null) {
            $this->stderr("No successful collection found for: {$slug}\n");
            return ExitCode::DATAERR;
        }

        $filePath = $collectionRun['file_path'];
        if (!file_exists($filePath)) {
            $this->stderr("Datapack file not found: {$filePath}\n");
            return ExitCode::DATAERR;
        }

        try {
            // Load and parse datapack
            $datapackJson = file_get_contents($filePath);
            if ($datapackJson === false) {
                throw new \RuntimeException("Failed to read datapack file: {$filePath}");
            }

            $datapackArray = Json::decode($datapackJson);
            $dataPack = IndustryDataPackFactory::fromArray($datapackArray);

            // Load thresholds from policy if available
            $thresholds = $this->loadThresholds($group);

            // Run analysis
            $request = new AnalyzeReportRequest($dataPack, $focalTicker, $thresholds);
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

            // Output report
            $reportJson = Json::encode($result->report->toArray(), JSON_PRETTY_PRINT);

            if ($this->output !== null) {
                file_put_contents($this->output, $reportJson);
                $this->stdout("Report written to: {$this->output}\n");
            } else {
                $this->stdout($reportJson . "\n");
            }

            $duration = microtime(true) - $startedAt;
            $this->logger->log(
                [
                    'message' => 'Analysis completed',
                    'slug' => $slug,
                    'focal' => $focalTicker,
                    'rating' => $result->report->focalAnalysis->rating->value,
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
                    'focal' => $focalTicker,
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
     * @param array<string, mixed> $group
     */
    private function loadThresholds(array $group): AnalysisThresholds
    {
        $policyId = $group['collection_policy_id'] ?? null;
        if ($policyId === null) {
            return new AnalysisThresholds();
        }

        $thresholdsJson = $this->collectionPolicyQuery->findAnalysisThresholds((int) $policyId);
        if ($thresholdsJson === null) {
            return new AnalysisThresholds();
        }

        $policyData = Json::decode($thresholdsJson);

        return AnalysisThresholds::fromPolicy($policyData);
    }
}
