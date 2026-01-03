<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Database seeder for initial/demo data.
 */
final class SeedController extends Controller
{
    /**
     * Runs all seeders.
     *
     * Usage: yii seed/all
     */
    public function actionAll(): int
    {
        $this->stdout("Running all seeders...\n\n");

        $result = $this->actionOilMajors();
        if ($result !== ExitCode::OK) {
            return $result;
        }

        // Add more seeders here as needed:
        // $this->actionOtherSeeder();

        $this->stdout("\nAll seeders completed.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Seeds the Global Energy Supermajors peer group.
     *
     * Creates: collection policy, peer group, companies, and memberships.
     * Safe to run multiple times - checks for existing data.
     */
    public function actionOilMajors(): int
    {
        $this->stdout("Seeding Oil Majors peer group...\n");

        // Check if already seeded
        $exists = (bool) Yii::$app->db->createCommand(
            'SELECT 1 FROM collection_policy WHERE slug = :slug'
        )->bindValue(':slug', 'global-energy-supermajors')->queryScalar();

        if ($exists) {
            $this->stdout("  Already seeded.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $sqlFile = Yii::getAlias('@app/../../docs/queries/oil_majors_setup.sql');

        if (!file_exists($sqlFile)) {
            $this->stderr("SQL file not found: {$sqlFile}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $sql = file_get_contents($sqlFile);

        try {
            $pdo = Yii::$app->db->getMasterPdo();
            $pdo->exec($sql);

            $this->stdout("  Seeded: collection policy, peer group, 5 companies\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stderr("Seeding failed: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Lists available seeders.
     */
    public function actionIndex(): int
    {
        $this->stdout("Seeders:\n\n");
        $this->stdout("  yii seed/all           Run all seeders\n");
        $this->stdout("  yii seed/oil-majors    Seed Global Energy Supermajors\n");
        $this->stdout("  yii seed/clear         Clear all seed data\n");
        $this->stdout("\nDatabase setup:\n\n");
        $this->stdout("  yii db/init            Run migrations + all seeders\n");
        $this->stdout("  yii db/reset           Drop tables, migrate, and seed\n");
        $this->stdout("\n");

        return ExitCode::OK;
    }

    /**
     * Clears all seeded data (use with caution).
     */
    public function actionClear(): int
    {
        if (!$this->confirm('This will delete ALL peer groups, companies, and policies. Continue?')) {
            $this->stdout("Cancelled.\n");
            return ExitCode::OK;
        }

        $db = Yii::$app->db;

        $db->createCommand('DELETE FROM industry_peer_group_member')->execute();
        $db->createCommand('DELETE FROM industry_peer_group')->execute();
        $db->createCommand('DELETE FROM company')->execute();
        $db->createCommand('DELETE FROM collection_policy')->execute();

        $this->stdout("All seed data cleared.\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }
}
