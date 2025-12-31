<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Adds created_by and updated_by audit columns to industry_config.
 *
 * Stores the authenticated username (VARCHAR 255) to track admin mutations.
 */
class m251230_230723_add_audit_columns_to_industry_config extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%industry_config}}',
            'created_by',
            $this->string(255)->null()->after('updated_at')
        );

        $this->addColumn(
            '{{%industry_config}}',
            'updated_by',
            $this->string(255)->null()->after('created_by')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%industry_config}}', 'updated_by');
        $this->dropColumn('{{%industry_config}}', 'created_by');
    }
}
