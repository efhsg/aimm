<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates data_source table for referential integrity on provider_id columns.
 *
 * This migration:
 * 1. Creates the data_source master table with known providers
 * 2. Adds foreign key constraints to all tables with provider_id columns
 */
final class m260110_000004_create_data_source_table extends Migration
{
    private const TABLES_WITH_PROVIDER_ID = [
        'valuation_snapshot',
        'annual_financial',
        'quarterly_financial',
        'ttm_financial',
        'price_history',
        'macro_indicator',
    ];

    public function safeUp(): void
    {
        // 1. Create data_source table
        $this->createTable('{{%data_source}}', [
            'id' => $this->string(50)->notNull(),
            'name' => $this->string(100)->notNull(),
            'source_type' => $this->string(20)->notNull()->comment('api, web_scrape, derived'),
            'base_url' => $this->string(255)->defaultValue(null),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'notes' => $this->text()->defaultValue(null),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP')
                ->append('ON UPDATE CURRENT_TIMESTAMP'),
            'PRIMARY KEY ([[id]])',
        ]);

        // 2. Seed with known providers
        $this->batchInsert('{{%data_source}}', ['id', 'name', 'source_type', 'base_url', 'notes'], [
            // API providers
            ['fmp', 'Financial Modeling Prep', 'api', 'https://financialmodelingprep.com', 'Primary API for US equities'],
            ['yahoo_finance', 'Yahoo Finance', 'web_scrape', 'https://finance.yahoo.com', 'Backup for valuations and quotes'],
            ['ecb', 'European Central Bank', 'api', 'https://data.ecb.europa.eu', 'FX rates (EUR base)'],
            ['eia_inventory', 'EIA Petroleum Inventory', 'api', 'https://api.eia.gov', 'US oil inventory data'],

            // Web scrape providers
            ['stockanalysis', 'Stock Analysis', 'web_scrape', 'https://stockanalysis.com', 'Valuation metrics fallback'],
            ['seeking_alpha', 'Seeking Alpha', 'web_scrape', 'https://seekingalpha.com', 'Analysis and metrics'],
            ['bloomberg', 'Bloomberg', 'web_scrape', 'https://bloomberg.com', 'Market data'],
            ['reuters', 'Reuters', 'web_scrape', 'https://reuters.com', 'Financial news and data'],
            ['morningstar', 'Morningstar', 'web_scrape', 'https://morningstar.com', 'Fund and stock analysis'],
            ['wsj', 'Wall Street Journal', 'web_scrape', 'https://wsj.com', 'Market data'],
            ['baker_hughes_rig_count', 'Baker Hughes Rig Count', 'web_scrape', 'https://bakerhughes.com', 'Oil rig count data'],

            // Internal/derived
            ['derived', 'Derived/Calculated', 'derived', null, 'Values calculated from other data points'],
        ]);

        // 3. Add foreign key constraints to all tables with provider_id
        foreach (self::TABLES_WITH_PROVIDER_ID as $table) {
            $this->addForeignKey(
                "fk_{$table}_provider",
                "{{%{$table}}}",
                'provider_id',
                '{{%data_source}}',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        }
    }

    public function safeDown(): void
    {
        // Remove foreign keys first
        foreach (self::TABLES_WITH_PROVIDER_ID as $table) {
            $this->dropForeignKey("fk_{$table}_provider", "{{%{$table}}}");
        }

        // Drop the table
        $this->dropTable('{{%data_source}}');
    }
}
