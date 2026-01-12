<?php

declare(strict_types=1);

namespace app\models;

use app\queries\CollectionAttemptQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the collection_attempt table.
 *
 * @property int $id
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string $data_type
 * @property string $source_adapter
 * @property string $source_url
 * @property string $outcome
 * @property int|null $http_status
 * @property string|null $error_message
 * @property string $attempted_at
 * @property int|null $duration_ms
 * @property string $created_at
 */
final class CollectionAttempt extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%collection_attempt}}';
    }

    public function rules(): array
    {
        return [
            [['entity_type', 'data_type', 'source_adapter', 'source_url', 'outcome', 'attempted_at'], 'required'],
            [['entity_id', 'http_status', 'duration_ms'], 'integer'],
            [['attempted_at', 'created_at'], 'safe'],
            [['entity_type', 'outcome'], 'string', 'max' => 255],
            [['data_type', 'source_adapter'], 'string', 'max' => 50],
            [['source_url', 'error_message'], 'string', 'max' => 500],
        ];
    }

    public static function find(): CollectionAttemptQuery
    {
        return new CollectionAttemptQuery(static::class);
    }
}
