<?php

declare(strict_types=1);

namespace app\models;

use app\enums\PdfJobStatus;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the pdf_job table.
 *
 * Tracks PDF generation jobs with status, error handling, and idempotency.
 *
 * @property int $id
 * @property string $report_id
 * @property string $params_hash
 * @property string|null $requester_id
 * @property string $status
 * @property string $trace_id
 * @property string|null $output_uri
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $attempts
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $finished_at
 */
class PdfJob extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%pdf_job}}';
    }

    public function rules(): array
    {
        return [
            [['report_id', 'params_hash', 'trace_id'], 'required'],
            [['report_id', 'trace_id'], 'string', 'max' => 50],
            [['params_hash'], 'string', 'max' => 64],
            [['requester_id'], 'string', 'max' => 100],
            [['status'], 'string', 'max' => 20],
            [['status'], 'default', 'value' => PdfJobStatus::Pending->value],
            [['status'], 'in', 'range' => array_column(PdfJobStatus::cases(), 'value')],
            [['output_uri'], 'string', 'max' => 500],
            [['error_code'], 'string', 'max' => 50],
            [['error_message'], 'string'],
            [['attempts'], 'integer', 'min' => 0],
            [['created_at', 'updated_at', 'finished_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'report_id' => 'Report ID',
            'params_hash' => 'Parameters Hash',
            'requester_id' => 'Requester ID',
            'status' => 'Status',
            'trace_id' => 'Trace ID',
            'output_uri' => 'Output URI',
            'error_code' => 'Error Code',
            'error_message' => 'Error Message',
            'attempts' => 'Attempts',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'finished_at' => 'Finished At',
        ];
    }

    /**
     * Get the status as an enum.
     */
    public function getStatusEnum(): PdfJobStatus
    {
        return PdfJobStatus::from($this->status);
    }

    /**
     * Check if the job is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->getStatusEnum()->isTerminal();
    }

    /**
     * Check if the job can transition to the given status.
     */
    public function canTransitionTo(PdfJobStatus $target): bool
    {
        return $this->getStatusEnum()->canTransitionTo($target);
    }
}
