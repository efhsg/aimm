<?php

declare(strict_types=1);

namespace app\models;

use app\models\query\IndustryConfigQuery;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * ActiveRecord model for the industry_config table.
 *
 * @property int $id
 * @property string $industry_id
 * @property string $name
 * @property string $config_json
 * @property bool $is_active
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read CollectionRun[] $collectionRuns
 */
final class IndustryConfig extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%industry_config}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['industry_id', 'name', 'config_json'], 'required'],
            [['industry_id'], 'string', 'max' => 64],
            [['industry_id'], 'unique'],
            [['name'], 'string', 'max' => 255],
            [['config_json'], 'string'],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'industry_id' => 'Industry ID',
            'name' => 'Name',
            'config_json' => 'Configuration',
            'is_active' => 'Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getCollectionRuns(): ActiveQuery
    {
        return $this->hasMany(CollectionRun::class, ['industry_id' => 'industry_id']);
    }

    public static function find(): IndustryConfigQuery
    {
        return new IndustryConfigQuery(static::class);
    }
}
