<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds EODHD (EOD Historical Data) to data_source table.
 *
 * EODHD provides dividend history and stock splits data with 30+ years of coverage.
 */
final class m260111_004815_add_eodhd_data_source extends Migration
{
    public function safeUp(): void
    {
        $this->insert('{{%data_source}}', [
            'id' => 'eodhd',
            'name' => 'EOD Historical Data',
            'source_type' => 'api',
            'base_url' => 'https://eodhd.com',
            'is_active' => true,
            'notes' => 'Dividends and splits history (20 req/day free tier)',
        ]);
    }

    public function safeDown(): void
    {
        $this->delete('{{%data_source}}', ['id' => 'eodhd']);
    }
}
