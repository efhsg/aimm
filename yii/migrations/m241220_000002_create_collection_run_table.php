<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the collection_run table for tracking collection executions.
 */
class m241220_000002_create_collection_run_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%collection_run}}', [
            'id' => $this->primaryKey(),
            'industry_id' => $this->string(64)->notNull(),
            'datapack_id' => $this->string(36)->notNull()->unique(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'started_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'completed_at' => $this->timestamp()->null(),
            'companies_total' => $this->integer()->notNull()->defaultValue(0),
            'companies_success' => $this->integer()->notNull()->defaultValue(0),
            'companies_failed' => $this->integer()->notNull()->defaultValue(0),
            'gate_passed' => $this->boolean()->null(),
            'error_count' => $this->integer()->notNull()->defaultValue(0),
            'warning_count' => $this->integer()->notNull()->defaultValue(0),
            'file_path' => $this->string(512)->null(),
            'file_size_bytes' => $this->bigInteger()->null(),
            'duration_seconds' => $this->integer()->null(),
        ]);

        $this->addForeignKey(
            'fk-collection_run-industry_id',
            '{{%collection_run}}',
            'industry_id',
            '{{%industry_config}}',
            'industry_id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx-collection_run-status',
            '{{%collection_run}}',
            'status'
        );

        $this->createIndex(
            'idx-collection_run-started_at',
            '{{%collection_run}}',
            'started_at'
        );

        $this->createIndex(
            'idx-collection_run-gate_passed',
            '{{%collection_run}}',
            'gate_passed'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%collection_run}}');
    }
}
