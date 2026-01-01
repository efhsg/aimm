<?php

declare(strict_types=1);

namespace app\commands;

use app\dto\CollectIndustryRequest;
use app\dto\IndustryConfig;
use app\enums\CollectionStatus;
use app\handlers\collection\CollectIndustryInterface;
use app\queries\IndustryConfigQuery;
use Throwable;
use Yii;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\log\Logger;

final class CollectController extends Controller
{
    private const LOG_CATEGORY = 'collection';
    private const HEADER_INDUSTRY = 'Industry';
    private const HEADER_DATAPACK = 'Datapack ID';
    private const HEADER_STATUS = 'Status';
    private const HEADER_DURATION = 'Duration';
    private const DURATION_FORMAT = '%.2fs';

    public ?string $focal = null;

    public function __construct(
        string $id,
        Module $module,
        private CollectIndustryInterface $collector,
        private IndustryConfigQuery $industryConfigQuery,
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
        return array_merge(parent::options($actionID), ['focal']);
    }

    public function actionIndustry(string $id): int
    {
        $startedAt = microtime(true);
        $config = $this->industryConfigQuery->findById($id);

        if ($config === null) {
            $this->logger->log(
                [
                    'message' => 'Industry config not found',
                    'industry' => $id,
                ],
                Logger::LEVEL_WARNING,
                self::LOG_CATEGORY
            );
            $this->stderr("Industry config not found: {$id}\n");
            return ExitCode::DATAERR;
        }

        Yii::$app->params['collectionIndustryId'] = $id;

        // Use IndustryConfig's resolution logic which respects:
        // 1. CLI override (--focal=TICKER)
        // 2. Config's focal_ticker field
        // 3. First company as fallback
        $cliOverride = is_string($this->focal) && $this->focal !== ''
            ? strtoupper(trim($this->focal))
            : null;
        $focalTicker = $config->resolveFocalTicker($cliOverride);
        Yii::$app->params['collectionFocalTicker'] = $focalTicker;

        try {
            $result = $this->collector->collect(
                new CollectIndustryRequest(
                    config: $config,
                    focalTicker: $focalTicker,
                )
            );
        } catch (Throwable $exception) {
            $this->logger->log(
                [
                    'message' => 'Industry collection failed',
                    'industry' => $id,
                    'error' => $exception->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                self::LOG_CATEGORY
            );
            $this->stderr('Industry collection failed: ' . $exception->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $duration = microtime(true) - $startedAt;
        $this->stdout($this->renderSummaryTable(
            $config->id,
            $result->datapackId,
            $result->overallStatus->value,
            $duration
        ));

        return $result->overallStatus === CollectionStatus::Complete
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }

    private function renderSummaryTable(
        string $industryId,
        string $datapackId,
        string $status,
        float $durationSeconds
    ): string {
        $headers = [
            self::HEADER_INDUSTRY,
            self::HEADER_DATAPACK,
            self::HEADER_STATUS,
            self::HEADER_DURATION,
        ];
        $values = [
            $industryId,
            $datapackId,
            $status,
            sprintf(self::DURATION_FORMAT, $durationSeconds),
        ];

        $widths = [];
        foreach ($headers as $index => $header) {
            $widths[$index] = max(strlen($header), strlen($values[$index]));
        }

        $separator = '+' . implode('+', array_map(
            static fn (int $width): string => str_repeat('-', $width + 2),
            $widths
        )) . "+\n";

        return $separator
            . $this->formatRow($headers, $widths)
            . $separator
            . $this->formatRow($values, $widths)
            . $separator;
    }

    /**
     * @param string[] $values
     * @param int[] $widths
     */
    private function formatRow(array $values, array $widths): string
    {
        $cells = [];
        foreach ($values as $index => $value) {
            $cells[] = str_pad($value, $widths[$index]);
        }

        return '| ' . implode(' | ', $cells) . " |\n";
    }
}
