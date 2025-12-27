<?php

declare(strict_types=1);

namespace app\models;

use app\models\query\SourceBlockQuery;
use DateTimeImmutable;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the source_block table.
 *
 * @property int $id
 * @property string $domain
 * @property string $blocked_at
 * @property string $blocked_until
 * @property int $consecutive_count
 * @property int|null $last_status_code
 * @property string|null $last_error
 */
final class SourceBlock extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%source_block}}';
    }

    public function rules(): array
    {
        return [
            [['domain', 'blocked_until'], 'required'],
            [['domain'], 'string', 'max' => 255],
            [['domain'], 'unique'],
            [['consecutive_count'], 'integer'],
            [['consecutive_count'], 'default', 'value' => 1],
            [['last_status_code'], 'integer'],
            [['last_error'], 'string'],
            [['blocked_at', 'blocked_until'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'domain' => 'Domain',
            'blocked_at' => 'Blocked At',
            'blocked_until' => 'Blocked Until',
            'consecutive_count' => 'Consecutive Count',
            'last_status_code' => 'Last Status Code',
            'last_error' => 'Last Error',
        ];
    }

    public static function find(): SourceBlockQuery
    {
        return new SourceBlockQuery(static::class);
    }

    public function isExpired(): bool
    {
        $blockedUntil = new DateTimeImmutable($this->blocked_until);

        return $blockedUntil < new DateTimeImmutable();
    }

    public function extendBlock(DateTimeImmutable $until, ?int $statusCode = null, ?string $error = null): void
    {
        $this->blocked_until = $until->format('Y-m-d H:i:s');
        $this->consecutive_count++;

        if ($statusCode !== null) {
            $this->last_status_code = $statusCode;
        }

        if ($error !== null) {
            $this->last_error = $error;
        }
    }

    public static function blockDomain(
        string $domain,
        DateTimeImmutable $until,
        ?int $statusCode = null,
        ?string $error = null,
    ): self {
        $block = self::findOne(['domain' => $domain]);

        if ($block === null) {
            $block = new self();
            $block->domain = $domain;
            $block->blocked_until = $until->format('Y-m-d H:i:s');
            $block->last_status_code = $statusCode;
            $block->last_error = $error;
        } else {
            $block->extendBlock($until, $statusCode, $error);
        }

        $block->save();

        return $block;
    }
}
