<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds data_as_of column to analysis_report table.
 *
 * Stores the timestamp of the most recent company data used in the analysis,
 * enabling staleness checks and data provenance queries.
 */
final class m260110_000001_add_data_as_of_to_analysis_report extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%analysis_report}}',
            'data_as_of',
            $this->dateTime()->null()->after('generated_at')
        );

        $this->createIndex(
            'idx_analysis_report_data_as_of',
            '{{%analysis_report}}',
            'data_as_of'
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_analysis_report_data_as_of', '{{%analysis_report}}');
        $this->dropColumn('{{%analysis_report}}', 'data_as_of');
    }
}
