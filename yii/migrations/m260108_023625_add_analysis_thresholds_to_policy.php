<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Add analysis_thresholds column to collection_policy table.
 *
 * Allows per-policy customization of analysis thresholds.
 */
final class m260108_023625_add_analysis_thresholds_to_policy extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%collection_policy}}',
            'analysis_thresholds',
            $this->json()->defaultValue(null)->after('optional_indicators')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%collection_policy}}', 'analysis_thresholds');
    }
}
