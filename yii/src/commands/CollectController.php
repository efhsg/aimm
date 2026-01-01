<?php

declare(strict_types=1);

namespace app\commands;

use app\dto\peergroup\CollectPeerGroupRequest;
use app\enums\CollectionStatus;
use app\handlers\peergroup\CollectPeerGroupInterface;
use app\queries\PeerGroupQuery;
use Throwable;
use Yii;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\log\Logger;

final class CollectController extends Controller
{
    private const LOG_CATEGORY = 'collection';
    private const HEADER_GROUP = 'Peer Group';
    private const HEADER_DATAPACK = 'Datapack ID';
    private const HEADER_STATUS = 'Status';
    private const HEADER_DURATION = 'Duration';
    private const DURATION_FORMAT = '%.2fs';

    public ?string $focal = null;

    public function __construct(
        string $id,
        Module $module,
        private CollectPeerGroupInterface $collector,
        private PeerGroupQuery $peerGroupQuery,
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

    /**
     * Collect data for a peer group.
     *
     * @param string $slug The peer group slug
     */
    public function actionPeerGroup(string $slug): int
    {
        $startedAt = microtime(true);
        $group = $this->peerGroupQuery->findBySlug($slug);

        if ($group === null) {
            $this->logger->log(
                [
                    'message' => 'Peer group not found',
                    'slug' => $slug,
                ],
                Logger::LEVEL_WARNING,
                self::LOG_CATEGORY
            );
            $this->stderr("Peer group not found: {$slug}\n");
            return ExitCode::DATAERR;
        }

        Yii::$app->params['collectionIndustryId'] = $slug;

        $cliOverride = is_string($this->focal) && $this->focal !== ''
            ? strtoupper(trim($this->focal))
            : null;
        Yii::$app->params['collectionFocalTicker'] = $cliOverride;

        try {
            $result = $this->collector->collect(
                new CollectPeerGroupRequest(
                    groupId: (int) $group['id'],
                    actorUsername: 'cli',
                    focalTickerOverride: $cliOverride,
                )
            );
        } catch (Throwable $exception) {
            $this->logger->log(
                [
                    'message' => 'Peer group collection failed',
                    'slug' => $slug,
                    'error' => $exception->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                self::LOG_CATEGORY
            );
            $this->stderr('Peer group collection failed: ' . $exception->getMessage() . "\n");
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
        string $groupSlug,
        string $datapackId,
        string $status,
        float $durationSeconds
    ): string {
        $headers = [
            self::HEADER_GROUP,
            self::HEADER_DATAPACK,
            self::HEADER_STATUS,
            self::HEADER_DURATION,
        ];
        $values = [
            $groupSlug,
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
