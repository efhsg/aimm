<?php

declare(strict_types=1);

namespace app\models;

use app\models\query\IndustryConfigQuery;
use app\validators\IndustryConfigJsonValidator;
use app\validators\SchemaValidatorInterface;
use Yii;
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
 * @property string|null $created_by
 * @property string|null $updated_by
 *
 * @property-read CollectionRun[] $collectionRuns
 */
final class IndustryConfig extends ActiveRecord
{
    public const SCENARIO_CREATE = 'create';
    public const SCENARIO_UPDATE = 'update';
    public const SCENARIO_TOGGLE = 'toggle';

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

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        $scenarios[self::SCENARIO_CREATE] = [
            'industry_id',
            'name',
            'config_json',
            'is_active',
            'created_by',
            'updated_by',
        ];

        $scenarios[self::SCENARIO_UPDATE] = [
            'name',
            'config_json',
            'is_active',
            'updated_by',
        ];

        $scenarios[self::SCENARIO_TOGGLE] = [
            'is_active',
            'updated_by',
        ];

        return $scenarios;
    }

    public function rules(): array
    {
        return [
            [['industry_id', 'config_json'], 'required'],
            [['industry_id'], 'string', 'max' => 64],
            [['industry_id'], 'match', 'pattern' => '/^[a-z0-9_-]+$/'],
            [['industry_id'], 'unique'],
            [['name'], 'string', 'max' => 255],
            [['config_json'], 'string'],
            [
                ['config_json'],
                IndustryConfigJsonValidator::class,
                'schemaValidator' => Yii::$container->get(SchemaValidatorInterface::class),
                'on' => [self::SCENARIO_CREATE, self::SCENARIO_UPDATE],
            ],
            [['is_active'], 'boolean'],
            [['is_active'], 'default', 'value' => true],
            [['created_by', 'updated_by'], 'string', 'max' => 255],
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
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
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

    /**
     * Override updateInternal to fix Yii2 bug with PHP 8.1+ deprecation.
     *
     * The issue is that optimisticLock() returns null by default, and
     * Yii2 uses `isset($values[$lock])` which triggers "Using null as
     * an array offset is deprecated" in PHP 8.1+.
     *
     * @see https://github.com/yiisoft/yii2/issues/19867
     * @inheritdoc
     */
    protected function updateInternal($attributes = null): int|false
    {
        if (!$this->beforeSave(false)) {
            return false;
        }

        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }

        $condition = $this->getOldPrimaryKey(true);
        $lock = $this->optimisticLock();

        if ($lock !== null) {
            $values[$lock] = $this->$lock + 1;
            $condition[$lock] = $this->$lock;
        }

        $rows = static::updateAll($values, $condition);

        if ($lock !== null && !$rows) {
            throw new \yii\db\StaleObjectException('The object being updated is outdated.');
        }

        // Fix: Check $lock is not null before using as array offset
        if ($lock !== null && isset($values[$lock])) {
            $this->$lock = $values[$lock];
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = $this->getOldAttribute($name);
            $this->setOldAttribute($name, $value);
        }

        $this->afterSave(false, $changedAttributes);

        return $rows;
    }
}
