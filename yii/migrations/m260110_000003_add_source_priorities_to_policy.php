<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds source_priorities column to collection_policy for per-industry source management.
 *
 * Schema:
 * {
 *   "valuation": ["fmp", "yahoo_finance", "stockanalysis"],
 *   "financials": ["fmp", "yahoo_finance"],
 *   "quarters": ["fmp", "yahoo_finance"],
 *   "macro": ["ecb", "eia"],
 *   "benchmarks": ["yahoo_finance", "fmp"]
 * }
 */
final class m260110_000003_add_source_priorities_to_policy extends Migration
{
    public function safeUp(): bool
    {
        $this->addColumn(
            'collection_policy',
            'source_priorities',
            $this->json()->null()->after('optional_indicators')
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('collection_policy', 'source_priorities');

        return true;
    }
}
