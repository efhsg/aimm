<?php

declare(strict_types=1);

namespace app\models;

use app\models\query\CollectionRunQuery;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the collection_run table.
 *
 * @property int $id
 * @property string $industry_id
 * @property string $datapack_id
 * @property string $status
 * @property string $started_at
 * @property string|null $completed_at
 * @property int $companies_total
 * @property int $companies_success
 * @property int $companies_failed
 * @property bool|null $gate_passed
 * @property int $error_count
 * @property int $warning_count
 * @property string|null $file_path
 * @property int|null $file_size_bytes
 * @property int|null $duration_seconds
 *
 * @property-read IndustryConfig $industryConfig
 * @property-read CollectionError[] $collectionErrors
 */
final class CollectionRun extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%collection_run}}';
    }

    public function rules(): array
    {
        return [
            [['industry_id', 'datapack_id'], 'required'],
            [['industry_id'], 'string', 'max' => 64],
            [['datapack_id'], 'string', 'max' => 36],
            [['datapack_id'], 'unique'],
            [['status'], 'string', 'max' => 20],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['status'], 'in', 'range' => [
                self::STATUS_PENDING,
                self::STATUS_RUNNING,
                self::STATUS_COMPLETE,
                self::STATUS_PARTIAL,
                self::STATUS_FAILED,
            ]],
            [['companies_total', 'companies_success', 'companies_failed', 'error_count', 'warning_count'], 'integer'],
            [['companies_total', 'companies_success', 'companies_failed', 'error_count', 'warning_count'], 'default', 'value' => 0],
            [['gate_passed'], 'boolean'],
            [['file_path'], 'string', 'max' => 512],
            [['file_size_bytes', 'duration_seconds'], 'integer'],
            [['started_at', 'completed_at'], 'safe'],
            [['industry_id'], 'exist', 'targetClass' => IndustryConfig::class, 'targetAttribute' => 'industry_id'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'industry_id' => 'Industry ID',
            'datapack_id' => 'Datapack ID',
            'status' => 'Status',
            'started_at' => 'Started At',
            'completed_at' => 'Completed At',
            'companies_total' => 'Companies Total',
            'companies_success' => 'Companies Success',
            'companies_failed' => 'Companies Failed',
            'gate_passed' => 'Gate Passed',
            'error_count' => 'Error Count',
            'warning_count' => 'Warning Count',
            'file_path' => 'File Path',
            'file_size_bytes' => 'File Size (bytes)',
            'duration_seconds' => 'Duration (seconds)',
        ];
    }

    public function getIndustryConfig(): ActiveQuery
    {
        return $this->hasOne(IndustryConfig::class, ['industry_id' => 'industry_id']);
    }

    public function getCollectionErrors(): ActiveQuery
    {
        return $this->hasMany(CollectionError::class, ['collection_run_id' => 'id']);
    }

    public static function find(): CollectionRunQuery
    {
        return new CollectionRunQuery(static::class);
    }

    public function markRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->save(false, ['status']);
    }

    public function markComplete(bool $gatePassed): void
    {
        $this->status = self::STATUS_COMPLETE;
        $this->gate_passed = $gatePassed;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save(false, ['status', 'gate_passed', 'completed_at']);
    }

    public function markPartial(): void
    {
        $this->status = self::STATUS_PARTIAL;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save(false, ['status', 'completed_at']);
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save(false, ['status', 'completed_at']);
    }
}
