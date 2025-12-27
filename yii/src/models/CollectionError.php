<?php

declare(strict_types=1);

namespace app\models;

use app\models\query\CollectionErrorQuery;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the collection_error table.
 *
 * @property int $id
 * @property int $collection_run_id
 * @property string $severity
 * @property string $error_code
 * @property string $error_message
 * @property string|null $error_path
 * @property string|null $ticker
 * @property string $created_at
 *
 * @property-read CollectionRun $collectionRun
 */
final class CollectionError extends ActiveRecord
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';

    public static function tableName(): string
    {
        return '{{%collection_error}}';
    }

    public function rules(): array
    {
        return [
            [['collection_run_id', 'error_code', 'error_message'], 'required'],
            [['collection_run_id'], 'integer'],
            [['severity'], 'string', 'max' => 20],
            [['severity'], 'default', 'value' => self::SEVERITY_ERROR],
            [['severity'], 'in', 'range' => [self::SEVERITY_ERROR, self::SEVERITY_WARNING]],
            [['error_code'], 'string', 'max' => 64],
            [['error_message'], 'string'],
            [['error_path'], 'string', 'max' => 255],
            [['ticker'], 'string', 'max' => 20],
            [['created_at'], 'safe'],
            [['collection_run_id'], 'exist', 'targetClass' => CollectionRun::class, 'targetAttribute' => 'id'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'collection_run_id' => 'Collection Run ID',
            'severity' => 'Severity',
            'error_code' => 'Error Code',
            'error_message' => 'Error Message',
            'error_path' => 'Error Path',
            'ticker' => 'Ticker',
            'created_at' => 'Created At',
        ];
    }

    public function getCollectionRun(): ActiveQuery
    {
        return $this->hasOne(CollectionRun::class, ['id' => 'collection_run_id']);
    }

    public static function find(): CollectionErrorQuery
    {
        return new CollectionErrorQuery(static::class);
    }

    public static function createError(
        int $collectionRunId,
        string $errorCode,
        string $errorMessage,
        ?string $errorPath = null,
        ?string $ticker = null,
    ): self {
        $error = new self();
        $error->collection_run_id = $collectionRunId;
        $error->severity = self::SEVERITY_ERROR;
        $error->error_code = $errorCode;
        $error->error_message = $errorMessage;
        $error->error_path = $errorPath;
        $error->ticker = $ticker;

        return $error;
    }

    public static function createWarning(
        int $collectionRunId,
        string $errorCode,
        string $errorMessage,
        ?string $errorPath = null,
        ?string $ticker = null,
    ): self {
        $error = new self();
        $error->collection_run_id = $collectionRunId;
        $error->severity = self::SEVERITY_WARNING;
        $error->error_code = $errorCode;
        $error->error_message = $errorMessage;
        $error->error_path = $errorPath;
        $error->ticker = $ticker;

        return $error;
    }
}
