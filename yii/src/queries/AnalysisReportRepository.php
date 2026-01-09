<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\report\RankedReportDTO;
use yii\db\Connection;
use yii\helpers\Json;

/**
 * Repository for analysis report persistence.
 */
final class AnalysisReportRepository implements AnalysisReportReader
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Save a ranked report for an industry.
     */
    public function saveRanked(int $industryId, RankedReportDTO $report): int
    {
        // Get the top-rated company for summary fields
        $topRated = $report->companyAnalyses[0] ?? null;

        $this->db->createCommand()->insert('{{%analysis_report}}', [
            'industry_id' => $industryId,
            'report_id' => $report->metadata->reportId,
            'rating' => $topRated?->rating->value ?? 'hold',
            'rule_path' => $topRated?->rulePath->value ?? 'unknown',
            'report_json' => Json::encode($report->toArray()),
            'generated_at' => $report->metadata->generatedAt->format('Y-m-d H:i:s'),
        ])->execute();

        return (int) $this->db->getLastInsertID();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%analysis_report}} WHERE id = :id'
        )->bindValue(':id', $id)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReportId(string $reportId): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%analysis_report}} WHERE report_id = :report_id'
        )->bindValue(':report_id', $reportId)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * Get the latest ranked report for an industry.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestRanking(int $industryId): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%analysis_report}}
             WHERE industry_id = :industry_id
             ORDER BY generated_at DESC
             LIMIT 1'
        )
            ->bindValue(':industry_id', $industryId)
            ->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * List reports for an industry.
     *
     * @return list<array<string, mixed>>
     */
    public function listByIndustry(int $industryId, int $limit = 20): array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%analysis_report}}
             WHERE industry_id = :industry_id
             ORDER BY generated_at DESC
             LIMIT :limit'
        )
            ->bindValue(':industry_id', $industryId)
            ->bindValue(':limit', $limit)
            ->queryAll();
    }

    /**
     * Decode the stored JSON report.
     *
     * @return array<string, mixed>
     */
    public function decodeReport(array $row): array
    {
        $data = $row['report_json'];

        // Handle double-encoded JSON or MySQL JSON column quirks
        while (is_string($data)) {
            $data = Json::decode($data, true);
        }

        return $data;
    }
}
