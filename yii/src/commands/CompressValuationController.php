<?php

declare(strict_types=1);

namespace app\commands;

use DateTimeImmutable;
use Yii;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\helpers\Console;
use yii\log\Logger;

/**
 * Compresses valuation snapshots according to retention policy.
 *
 * Retention tiers:
 * - Daily (0-30 days): Keep all snapshots
 * - Weekly (31-365 days): Keep only Friday snapshots
 * - Monthly (1+ years): Keep only month-end snapshots
 *
 * Usage: docker exec aimm_yii php yii compress-valuation
 */
final class CompressValuationController extends Controller
{
    private const LOG_CATEGORY = 'valuation-compression';
    private const DAILY_RETENTION_DAYS = 30;
    private const WEEKLY_RETENTION_DAYS = 365;
    private const FRIDAY_DAY_OF_WEEK = 5; // PHP: Monday=1, Sunday=7

    public bool $dryRun = false;

    public function __construct(
        string $id,
        Module $module,
        private readonly Connection $db,
        private readonly Logger $logger,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * @return list<string>
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'd' => 'dryRun',
        ]);
    }

    /**
     * Compresses valuation snapshots according to retention policy.
     */
    public function actionIndex(): int
    {
        $startedAt = microtime(true);
        $today = new DateTimeImmutable('today');
        $weeklyCutoff = $today->modify(sprintf('-%d days', self::DAILY_RETENTION_DAYS));
        $monthlyCutoff = $today->modify(sprintf('-%d days', self::WEEKLY_RETENTION_DAYS));

        $this->stdout("Valuation Snapshot Compression\n", Console::BOLD);
        $this->stdout("================================\n\n");

        if ($this->dryRun) {
            $this->stdout("[DRY RUN] No changes will be made.\n\n", Console::FG_YELLOW);
        }

        $this->stdout(sprintf("Daily retention: %s and newer\n", $weeklyCutoff->format('Y-m-d')));
        $this->stdout(sprintf("Weekly retention: %s to %s\n", $monthlyCutoff->format('Y-m-d'), $weeklyCutoff->format('Y-m-d')));
        $this->stdout(sprintf("Monthly retention: older than %s\n\n", $monthlyCutoff->format('Y-m-d')));

        $transaction = $this->db->beginTransaction();

        try {
            // Step 1: Weekly tier - promote Fridays to 'weekly', delete non-Fridays
            $weeklyPromoted = $this->promoteToWeeklyTier($weeklyCutoff, $monthlyCutoff);
            $weeklyDeleted = $this->deleteNonFridayDailies($weeklyCutoff, $monthlyCutoff);

            // Step 2: Monthly tier - promote month-end to 'monthly', delete non-month-end
            $monthlyPromoted = $this->promoteToMonthlyTier($monthlyCutoff);
            $monthlyDeleted = $this->deleteNonMonthEndWeeklies($monthlyCutoff);

            if ($this->dryRun) {
                $transaction->rollBack();
                $this->stdout("\n[DRY RUN] Transaction rolled back.\n", Console::FG_YELLOW);
            } else {
                $transaction->commit();
            }

            $duration = microtime(true) - $startedAt;

            $this->stdout("\nSummary\n", Console::BOLD);
            $this->stdout("-------\n");
            $this->stdout(sprintf("Weekly tier: %d promoted, %d deleted\n", $weeklyPromoted, $weeklyDeleted));
            $this->stdout(sprintf("Monthly tier: %d promoted, %d deleted\n", $monthlyPromoted, $monthlyDeleted));
            $this->stdout(sprintf("Duration: %.2fs\n", $duration));

            $this->logger->log(
                [
                    'message' => 'Valuation compression completed',
                    'weekly_promoted' => $weeklyPromoted,
                    'weekly_deleted' => $weeklyDeleted,
                    'monthly_promoted' => $monthlyPromoted,
                    'monthly_deleted' => $monthlyDeleted,
                    'dry_run' => $this->dryRun,
                    'duration_seconds' => $duration,
                ],
                Logger::LEVEL_INFO,
                self::LOG_CATEGORY
            );

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Valuation compression failed',
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                self::LOG_CATEGORY
            );

            $this->stderr("\nError: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Promote Friday snapshots in weekly range to 'weekly' tier.
     */
    private function promoteToWeeklyTier(DateTimeImmutable $weeklyCutoff, DateTimeImmutable $monthlyCutoff): int
    {
        $sql = <<<'SQL'
UPDATE valuation_snapshot
SET retention_tier = 'weekly'
WHERE snapshot_date < :weekly_cutoff
  AND snapshot_date >= :monthly_cutoff
  AND retention_tier = 'daily'
  AND DAYOFWEEK(snapshot_date) = 6
SQL;

        return $this->db->createCommand($sql, [
            ':weekly_cutoff' => $weeklyCutoff->format('Y-m-d'),
            ':monthly_cutoff' => $monthlyCutoff->format('Y-m-d'),
        ])->execute();
    }

    /**
     * Delete non-Friday daily snapshots in weekly range.
     */
    private function deleteNonFridayDailies(DateTimeImmutable $weeklyCutoff, DateTimeImmutable $monthlyCutoff): int
    {
        $sql = <<<'SQL'
DELETE FROM valuation_snapshot
WHERE snapshot_date < :weekly_cutoff
  AND snapshot_date >= :monthly_cutoff
  AND retention_tier = 'daily'
SQL;

        return $this->db->createCommand($sql, [
            ':weekly_cutoff' => $weeklyCutoff->format('Y-m-d'),
            ':monthly_cutoff' => $monthlyCutoff->format('Y-m-d'),
        ])->execute();
    }

    /**
     * Promote month-end snapshots in monthly range to 'monthly' tier.
     *
     * Month-end is defined as the last snapshot date in each month per company.
     */
    private function promoteToMonthlyTier(DateTimeImmutable $monthlyCutoff): int
    {
        // First, identify month-end snapshots per company
        $sql = <<<'SQL'
UPDATE valuation_snapshot vs
INNER JOIN (
    SELECT company_id, MAX(snapshot_date) AS month_end_date
    FROM valuation_snapshot
    WHERE snapshot_date < :monthly_cutoff
      AND retention_tier = 'weekly'
    GROUP BY company_id, YEAR(snapshot_date), MONTH(snapshot_date)
) month_ends ON vs.company_id = month_ends.company_id
            AND vs.snapshot_date = month_ends.month_end_date
SET vs.retention_tier = 'monthly'
WHERE vs.snapshot_date < :monthly_cutoff
  AND vs.retention_tier = 'weekly'
SQL;

        return $this->db->createCommand($sql, [
            ':monthly_cutoff' => $monthlyCutoff->format('Y-m-d'),
        ])->execute();
    }

    /**
     * Delete non-month-end weekly snapshots in monthly range.
     */
    private function deleteNonMonthEndWeeklies(DateTimeImmutable $monthlyCutoff): int
    {
        $sql = <<<'SQL'
DELETE FROM valuation_snapshot
WHERE snapshot_date < :monthly_cutoff
  AND retention_tier = 'weekly'
SQL;

        return $this->db->createCommand($sql, [
            ':monthly_cutoff' => $monthlyCutoff->format('Y-m-d'),
        ])->execute();
    }
}
