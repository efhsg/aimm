<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord model for data_source table.
 *
 * @property string $id
 * @property string $name
 * @property string $source_type
 * @property string|null $base_url
 * @property int $is_active
 * @property string|null $notes
 * @property string $created_at
 * @property string $updated_at
 */
final class DataSource extends ActiveRecord
{
    public const SOURCE_TYPE_API = 'api';
    public const SOURCE_TYPE_WEB_SCRAPE = 'web_scrape';
    public const SOURCE_TYPE_DERIVED = 'derived';

    public static function tableName(): string
    {
        return 'data_source';
    }

    public function rules(): array
    {
        return [
            [['id', 'name', 'source_type'], 'required'],
            [['id'], 'string', 'max' => 50],
            [['name'], 'string', 'max' => 100],
            [['source_type'], 'string', 'max' => 20],
            [['source_type'], 'in', 'range' => [
                self::SOURCE_TYPE_API,
                self::SOURCE_TYPE_WEB_SCRAPE,
                self::SOURCE_TYPE_DERIVED,
            ]],
            [['base_url'], 'string', 'max' => 255],
            [['base_url'], 'url', 'skipOnEmpty' => true],
            [['is_active'], 'boolean'],
            [['notes'], 'string'],
            [['id'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'source_type' => 'Source Type',
            'base_url' => 'Base URL',
            'is_active' => 'Active',
            'notes' => 'Notes',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
