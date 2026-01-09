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

        $result = $this->actionUsEnergyMajors();
        if ($result !== ExitCode::OK) {
            return $result;
        }

        $result = $this->actionUsTechGiants();
        if ($result !== ExitCode::OK) {
            return $result;
        }

        $this->stdout("\nAll seeders completed.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Seeds the Global Energy Supermajors industry.
     *
     * Creates: collection policy, industry, companies, and memberships.
     * Safe to run multiple times - checks for existing data.
     */
    public function actionOilMajors(): int
    {
        $this->stdout("Seeding Oil Majors industry...\n");

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

            $this->stdout("  Seeded: collection policy, industry, 5 companies\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stderr("Seeding failed: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Seeds the US Energy Majors industry (FMP free tier compatible).
     *
     * Creates: collection policy, industry, companies, and memberships.
     * All tickers are US-listed and work with FMP free tier.
     */
    public function actionUsEnergyMajors(): int
    {
        $this->stdout("Seeding US Energy Majors industry...\n");

        // Check if already seeded
        $exists = (bool) Yii::$app->db->createCommand(
            'SELECT 1 FROM collection_policy WHERE slug = :slug'
        )->bindValue(':slug', 'us-energy-majors')->queryScalar();

        if ($exists) {
            $this->stdout("  Already seeded.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $sqlFile = Yii::getAlias('@app/../../docs/queries/us_energy_majors_setup.sql');

        if (!file_exists($sqlFile)) {
            $this->stderr("SQL file not found: {$sqlFile}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $sql = file_get_contents($sqlFile);

        try {
            $pdo = Yii::$app->db->getMasterPdo();
            $pdo->exec($sql);

            $this->stdout("  Seeded: collection policy, industry, 5 US companies\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stderr("Seeding failed: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Seeds the US Tech Giants industry (FMP free tier compatible).
     *
     * Creates: collection policy, industry, companies, and memberships.
     * All tickers are large-cap tech and work with FMP free tier.
     */
    public function actionUsTechGiants(): int
    {
        $this->stdout("Seeding US Tech Giants industry...\n");

        // Check if already seeded
        $exists = (bool) Yii::$app->db->createCommand(
            'SELECT 1 FROM collection_policy WHERE slug = :slug'
        )->bindValue(':slug', 'us-tech-giants')->queryScalar();

        if ($exists) {
            $this->stdout("  Already seeded.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $sqlFile = Yii::getAlias('@app/../../docs/queries/us_tech_giants_setup.sql');

        if (!file_exists($sqlFile)) {
            $this->stderr("SQL file not found: {$sqlFile}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $sql = file_get_contents($sqlFile);

        try {
            $pdo = Yii::$app->db->getMasterPdo();
            $pdo->exec($sql);

            $this->stdout("  Seeded: collection policy, industry, 5 tech companies\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stderr("Seeding failed: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Seeds realistic test data for Global Energy Supermajors.
     *
     * Adds annual financials (5 years), quarterly financials (8 quarters),
     * and valuation snapshots for all 5 supermajors. Use this for phase 2
     * development when API quotas are exhausted.
     *
     * Requires: oil-majors industry must be seeded first.
     */
    public function actionSupermajorsTestdata(): int
    {
        $this->stdout("Seeding Global Energy Supermajors test data...\n");

        // Check if industry exists
        $industryExists = (bool) Yii::$app->db->createCommand(
            'SELECT 1 FROM industry WHERE slug = :slug'
        )->bindValue(':slug', 'global-energy-supermajors')->queryScalar();

        if (!$industryExists) {
            $this->stderr("  Industry 'global-energy-supermajors' not found.\n", Console::FG_RED);
            $this->stderr("  Run 'yii seed/oil-majors' first.\n", Console::FG_YELLOW);
            return ExitCode::DATAERR;
        }

        // Check if already seeded (look for seeded annual financials)
        $alreadySeeded = (bool) Yii::$app->db->createCommand(
            "SELECT 1 FROM annual_financial WHERE source_adapter = 'seed' LIMIT 1"
        )->queryScalar();

        if ($alreadySeeded) {
            $this->stdout("  Test data already seeded.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $sqlFile = Yii::getAlias('@app/../../docs/queries/global_supermajors_testdata.sql');

        if (!file_exists($sqlFile)) {
            $this->stderr("SQL file not found: {$sqlFile}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $sql = file_get_contents($sqlFile);

        try {
            $pdo = Yii::$app->db->getMasterPdo();
            $pdo->exec($sql);

            $this->stdout("  Seeded: 25 annual records (2021-2025), 40 quarterly records (2024-2025), 5 valuations\n", Console::FG_GREEN);
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
        $this->stdout("  yii seed/all                    Run all seeders\n");
        $this->stdout("  yii seed/oil-majors             Seed Global Energy Supermajors\n");
        $this->stdout("  yii seed/us-energy-majors       Seed US Energy Majors (FMP free tier)\n");
        $this->stdout("  yii seed/us-tech-giants         Seed US Tech Giants (FMP free tier)\n");
        $this->stdout("  yii seed/supermajors-testdata   Seed realistic test data (no API needed)\n");
        $this->stdout("  yii seed/clear                  Clear all seed data\n");
        $this->stdout("\nDatabase setup:\n\n");
        $this->stdout("  yii db/init                     Run migrations + all seeders\n");
        $this->stdout("  yii db/reset                    Drop tables, migrate, and seed\n");
        $this->stdout("\n");

        return ExitCode::OK;
    }

    /**
     * Clears all seeded data (use with caution).
     */
    public function actionClear(): int
    {
        if (!$this->confirm('This will delete ALL industries, companies, sectors, and policies. Continue?')) {
            $this->stdout("Cancelled.\n");
            return ExitCode::OK;
        }

        $db = Yii::$app->db;

        $db->createCommand('DELETE FROM company')->execute();
        $db->createCommand('DELETE FROM industry')->execute();
        $db->createCommand('DELETE FROM sector')->execute();
        $db->createCommand('DELETE FROM collection_policy')->execute();

        $this->stdout("All seed data cleared.\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }
}
