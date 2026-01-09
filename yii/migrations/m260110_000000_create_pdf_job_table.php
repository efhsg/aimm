<?php

declare(strict_types=1);

use yii\db\Expression;
use yii\db\Migration;

/**
 * Creates the pdf_job table for PDF generation job tracking.
 *
 * Provides idempotent job processing with status tracking and error handling.
 * References analysis_report for data source.
 */
final class m260110_000000_create_pdf_job_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%pdf_job}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'report_id' => $this->string(50)->notNull(),
            'params_hash' => $this->string(64)->notNull(),
            'requester_id' => $this->string(100)->defaultValue(null),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'trace_id' => $this->string(50)->notNull(),
            'output_uri' => $this->string(500)->defaultValue(null),
            'error_code' => $this->string(50)->defaultValue(null),
            'error_message' => $this->text()->defaultValue(null),
            'attempts' => $this->tinyInteger()->notNull()->defaultValue(0)->unsigned(),
            'created_at' => $this->dateTime()->notNull()->defaultValue(new Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->dateTime()->notNull()->defaultValue(new Expression('CURRENT_TIMESTAMP')),
            'finished_at' => $this->dateTime()->defaultValue(null),
        ]);

        // Idempotency: same report + params = same job
        $this->createIndex(
            'uk_pdf_job_report_params',
            '{{%pdf_job}}',
            ['report_id', 'params_hash'],
            true
        );

        // Status lookups for monitoring
        $this->createIndex('idx_pdf_job_status', '{{%pdf_job}}', ['status']);

        // Trace ID for observability
        $this->createIndex('idx_pdf_job_trace_id', '{{%pdf_job}}', ['trace_id']);

        // Cleanup queries by status and finish time
        $this->createIndex('idx_pdf_job_finished', '{{%pdf_job}}', ['status', 'finished_at']);

        // Foreign key to analysis_report
        $this->addForeignKey(
            'fk-pdf_job-report_id',
            '{{%pdf_job}}',
            ['report_id'],
            '{{%analysis_report}}',
            ['report_id'],
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-pdf_job-report_id', '{{%pdf_job}}');
        $this->dropTable('{{%pdf_job}}');
    }
}
