<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the collection_error table for recording errors and warnings during collection.
 */
class m241220_000003_create_collection_error_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%collection_error}}', [
            'id' => $this->primaryKey(),
            'collection_run_id' => $this->integer()->notNull(),
            'severity' => $this->string(20)->notNull()->defaultValue('error'),
            'error_code' => $this->string(64)->notNull(),
            'error_message' => $this->text()->notNull(),
            'error_path' => $this->string(255)->null(),
            'ticker' => $this->string(20)->null(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addForeignKey(
            'fk-collection_error-collection_run_id',
            '{{%collection_error}}',
            'collection_run_id',
            '{{%collection_run}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(
            'idx-collection_error-severity',
            '{{%collection_error}}',
            'severity'
        );

        $this->createIndex(
            'idx-collection_error-error_code',
            '{{%collection_error}}',
            'error_code'
        );

        $this->createIndex(
            'idx-collection_error-ticker',
            '{{%collection_error}}',
            'ticker'
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%collection_error}}');
    }
}
