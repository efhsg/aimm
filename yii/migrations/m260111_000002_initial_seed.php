<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Seeds initial reference data required to run the application.
 *
 * This migration populates:
 * - data_source: Known data providers (APIs, web scrapers)
 *
 * Additional seed tables (sector, industry, collection_policy) are populated
 * via application seeders or manually, as they vary by deployment.
 */
final class m260111_000002_initial_seed extends Migration
{
    public function safeUp(): void
    {
        // Data sources - required for provider_id foreign keys
        $this->batchInsert('{{%data_source}}', ['id', 'name', 'source_type', 'base_url', 'is_active', 'notes'], [
            // API providers
            ['fmp', 'Financial Modeling Prep', 'api', 'https://financialmodelingprep.com', 1, 'Primary API for US equities'],
            ['yahoo_finance', 'Yahoo Finance', 'web_scrape', 'https://finance.yahoo.com', 1, 'Backup for valuations and quotes'],
            ['ecb', 'European Central Bank', 'api', 'https://data.ecb.europa.eu', 1, 'FX rates (EUR base)'],
            ['eia_inventory', 'EIA Petroleum Inventory', 'api', 'https://api.eia.gov', 1, 'US oil inventory data'],
            ['eodhd', 'EOD Historical Data', 'api', 'https://eodhd.com', 1, 'Dividends and splits history (20 req/day free tier)'],

            // Web scrape providers
            ['stockanalysis', 'Stock Analysis', 'web_scrape', 'https://stockanalysis.com', 1, 'Valuation metrics fallback'],
            ['seeking_alpha', 'Seeking Alpha', 'web_scrape', 'https://seekingalpha.com', 1, 'Analysis and metrics'],
            ['bloomberg', 'Bloomberg', 'web_scrape', 'https://bloomberg.com', 1, 'Market data'],
            ['reuters', 'Reuters', 'web_scrape', 'https://reuters.com', 1, 'Financial news and data'],
            ['morningstar', 'Morningstar', 'web_scrape', 'https://morningstar.com', 1, 'Fund and stock analysis'],
            ['wsj', 'Wall Street Journal', 'web_scrape', 'https://wsj.com', 1, 'Market data'],
            ['baker_hughes_rig_count', 'Baker Hughes Rig Count', 'web_scrape', 'https://bakerhughes.com', 1, 'Oil rig count data'],

            // Internal/derived
            ['derived', 'Derived/Calculated', 'derived', null, 1, 'Values calculated from other data points'],
        ]);
    }

    public function safeDown(): void
    {
        $this->delete('{{%data_source}}', [
            'id' => [
                'fmp',
                'yahoo_finance',
                'ecb',
                'eia_inventory',
                'eodhd',
                'stockanalysis',
                'seeking_alpha',
                'bloomberg',
                'reuters',
                'morningstar',
                'wsj',
                'baker_hughes_rig_count',
                'derived',
            ],
        ]);
    }
}
