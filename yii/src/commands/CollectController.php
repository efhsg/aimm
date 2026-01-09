<?php

declare(strict_types=1);

namespace app\commands;

use app\dto\industry\CollectIndustryRequest;
use app\enums\CollectionStatus;
use app\handlers\industry\CollectIndustryInterface;
use app\queries\IndustryQuery;
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

    public function __construct(
        string $id,
        Module $module,
        private CollectIndustryInterface $collector,
        private IndustryQuery $industryQuery,
        private Logger $logger,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Collect data for an industry.
     *
     * @param string $slug The industry slug
     */
    public function actionIndustry(string $slug): int
    {
        $startedAt = microtime(true);
        $industry = $this->industryQuery->findBySlug($slug);

        if ($industry === null) {
            $this->logger->log(
                [
                    'message' => 'Industry not found',
                    'slug' => $slug,
                ],
                Logger::LEVEL_WARNING,
                self::LOG_CATEGORY
            );
            $this->stderr("Industry not found: {$slug}\n");
            return ExitCode::DATAERR;
        }

        Yii::$app->params['collectionIndustryId'] = $slug;

        try {
            $result = $this->collector->collect(
                new CollectIndustryRequest(
                    industryId: (int) $industry['id'],
                    actorUsername: 'cli',
                )
            );
        } catch (Throwable $exception) {
            $this->logger->log(
                [
                    'message' => 'Industry collection failed',
                    'slug' => $slug,
                    'error' => $exception->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                self::LOG_CATEGORY
            );
            $this->stderr('Industry collection failed: ' . $exception->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$result->success) {
            $this->stderr('Collection failed: ' . implode(', ', $result->errors) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $duration = microtime(true) - $startedAt;
        $this->stdout($this->renderSummaryTable(
            $slug,
            $result->datapackId ?? 'N/A',
            $result->status?->value ?? 'unknown',
            $duration
        ));

        return $result->status === CollectionStatus::Complete
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }

    private function renderSummaryTable(
        string $industrySlug,
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
            $industrySlug,
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
