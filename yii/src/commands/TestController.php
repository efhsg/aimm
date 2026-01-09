<?php

declare(strict_types=1);

namespace app\commands;

use app\clients\UrlSanitizer;
use app\factories\SourceCandidateFactory;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

final class TestController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout("AIMM is ready.\n");
        $this->stdout('PHP: ' . PHP_VERSION . "\n");
        return ExitCode::OK;
    }

    public function actionDb(): int
    {
        try {
            $value = (string) Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (Throwable $e) {
            $this->stderr('DB connection failed: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("DB OK: {$value}\n");
        return ExitCode::OK;
    }

    public function actionCompanies(): int
    {
        $companies = Yii::$app->db->createCommand('SELECT id, ticker, name, industry_id FROM company ORDER BY id')->queryAll();
        $this->stdout("Companies (" . count($companies) . "):\n");
        foreach ($companies as $row) {
            $industryId = $row['industry_id'] ?? 'NULL';
            $this->stdout("  ID {$row['id']}: {$row['ticker']} - {$row['name']} (industry_id: {$industryId})\n");
        }

        $this->stdout("\nCompanies by Industry:\n");
        $byIndustry = Yii::$app->db->createCommand(
            'SELECT g.slug AS industry_slug, g.name AS industry_name, c.id, c.ticker, c.name
             FROM company c
             INNER JOIN industry g ON c.industry_id = g.id
             ORDER BY g.slug, c.ticker
             LIMIT 20'
        )->queryAll();
        foreach ($byIndustry as $row) {
            $this->stdout("  [{$row['industry_slug']}] {$row['ticker']} - {$row['name']}\n");
        }

        return ExitCode::OK;
    }

    /**
     * Test FMP source candidates generation.
     */
    public function actionFmpSources(string $ticker = 'SHEL'): int
    {
        $factory = Yii::$container->get(SourceCandidateFactory::class);
        $sources = $factory->forFinancials($ticker);

        $this->stdout("Financial sources for {$ticker}:\n");
        foreach ($sources as $source) {
            $safeUrl = UrlSanitizer::sanitize($source->url);
            $this->stdout("  [{$source->priority}] {$source->adapterId}: {$safeUrl}\n");
        }

        return ExitCode::OK;
    }

    /**
     * Fix double-encoded JSON in collection_policy table.
     */
    public function actionFixPolicy(string $slug): int
    {
        $policy = Yii::$app->db->createCommand('SELECT * FROM collection_policy WHERE slug = :slug')
            ->bindValue(':slug', $slug)
            ->queryOne();

        if (!$policy) {
            $this->stderr("Policy not found: {$slug}\n");
            return ExitCode::DATAERR;
        }

        $this->stdout("Checking policy: {$policy['name']}\n");

        $columns = ['valuation_metrics', 'annual_financial_metrics', 'quarterly_financial_metrics', 'operational_metrics', 'required_indicators', 'optional_indicators'];
        $updates = [];

        foreach ($columns as $col) {
            $raw = $policy[$col] ?? null;
            if ($raw === null) {
                $this->stdout("  {$col}: NULL - skip\n");
                continue;
            }

            // Try to decode
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->stdout("  {$col}: Invalid JSON - skip\n");
                continue;
            }

            // Check if it's a string (double-encoded)
            if (is_string($decoded)) {
                $this->stdout("  {$col}: Double-encoded - fixing\n");
                // The decoded value is a string, so we need to use it directly (it's the actual JSON)
                $updates[$col] = $decoded;
            } else {
                $this->stdout("  {$col}: OK (is array)\n");
            }
        }

        if (empty($updates)) {
            $this->stdout("No fixes needed\n");
            return ExitCode::OK;
        }

        // Build SET clause manually to avoid any Yii encoding
        $setClauses = [];
        $params = [];
        foreach ($updates as $col => $val) {
            $setClauses[] = "`{$col}` = :{$col}";
            $params[":{$col}"] = $val;
        }
        $sql = 'UPDATE collection_policy SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $params[':id'] = $policy['id'];

        Yii::$app->db->createCommand($sql)
            ->bindValues($params)
            ->execute();

        $this->stdout("Fixed " . count($updates) . " columns\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
