<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Database management commands.
 */
final class DbController extends Controller
{
    /**
     * @var bool Skip confirmation prompts.
     */
    public bool $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['force']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['f' => 'force']);
    }

    /**
     * Initializes database: runs migrations and seeds all data.
     *
     * Usage: yii db/init
     */
    public function actionInit(): int
    {
        $this->stdout("Initializing database...\n\n", Console::FG_CYAN);

        // Run migrations
        $this->stdout("=== Running migrations ===\n", Console::FG_YELLOW);
        $migrateResult = Yii::$app->runAction('migrate', ['interactive' => false]);

        if ($migrateResult !== ExitCode::OK) {
            $this->stderr("Migration failed.\n", Console::FG_RED);
            return $migrateResult;
        }

        $this->stdout("\n");

        // Run all seeders
        $this->stdout("=== Seeding configuration ===\n", Console::FG_YELLOW);
        $seedResult = Yii::$app->runAction('seed/config', ['interactive' => false]);

        if ($seedResult !== ExitCode::OK) {
            return $seedResult;
        }

        $this->stdout("\nDatabase initialized successfully.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Resets database: clears all data, re-runs migrations and seeds.
     *
     * Usage: yii db/reset [-f|--force]
     */
    public function actionReset(): int
    {
        if (!$this->force && !$this->confirm('This will DROP all tables and recreate. Continue?')) {
            $this->stdout("Cancelled.\n");
            return ExitCode::OK;
        }

        $this->stdout("Resetting database...\n\n", Console::FG_CYAN);

        // Drop all migrations
        $this->stdout("=== Dropping tables ===\n", Console::FG_YELLOW);
        Yii::$app->runAction('migrate/down', ['all', 'interactive' => false]);

        $this->stdout("\n");

        // Re-init
        return $this->actionInit();
    }
}
