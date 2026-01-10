<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds provider_id column to data tables for explicit source tracking.
 *
 * This enables tracking which specific provider (fmp, yahoo_finance, ecb, etc.)
 * supplied each datapoint, rather than generic 'web_fetch' method.
 */
final class m260110_000002_add_provider_id_columns extends Migration
{
    /**
     * Tables with the column to place provider_id after.
     * Most tables have collected_at, but ttm_financial uses calculated_at.
     */
    private const TABLES = [
        'valuation_snapshot' => 'collected_at',
        'annual_financial' => 'collected_at',
        'quarterly_financial' => 'collected_at',
        'ttm_financial' => 'calculated_at',
        'price_history' => 'collected_at',
        'macro_indicator' => 'collected_at',
    ];

    public function safeUp(): bool
    {
        foreach (self::TABLES as $table => $afterColumn) {
            $this->addColumn(
                $table,
                'provider_id',
                $this->string(50)->null()->after($afterColumn)
            );
            $this->createIndex(
                "idx_{$table}_provider_id",
                $table,
                'provider_id'
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        foreach (self::TABLES as $table => $afterColumn) {
            $this->dropIndex("idx_{$table}_provider_id", $table);
            $this->dropColumn($table, 'provider_id');
        }

        return true;
    }
}
