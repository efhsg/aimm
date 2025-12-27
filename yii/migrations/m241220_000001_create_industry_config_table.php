<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the industry_config table for storing industry configuration.
 */
class m241220_000001_create_industry_config_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%industry_config}}', [
            'id' => $this->primaryKey(),
            'industry_id' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'config_yaml' => $this->text()->notNull(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'idx-industry_config-is_active',
            '{{%industry_config}}',
            'is_active'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%industry_config}}');
    }
}
