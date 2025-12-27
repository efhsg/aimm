<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the source_block table for tracking rate-limited/blocked domains.
 */
class m241220_000004_create_source_block_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%source_block}}', [
            'id' => $this->primaryKey(),
            'domain' => $this->string(255)->notNull()->unique(),
            'blocked_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'blocked_until' => $this->timestamp()->notNull(),
            'consecutive_count' => $this->integer()->notNull()->defaultValue(1),
            'last_status_code' => $this->integer()->null(),
            'last_error' => $this->text()->null(),
        ]);

        $this->createIndex(
            'idx-source_block-blocked_until',
            '{{%source_block}}',
            'blocked_until'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%source_block}}');
    }
}
