<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Repository for source block persistence (database-backed rate limiting).
 */
final class SourceBlockRepository implements SourceBlockRepositoryInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function isBlocked(string $domain): bool
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $row = $this->db->createCommand(
            'SELECT id FROM {{%source_block}}
             WHERE domain = :domain AND blocked_until > :now
             LIMIT 1',
        )
            ->bindValue(':domain', $domain)
            ->bindValue(':now', $now)
            ->queryOne();

        return $row !== false;
    }

    public function getBlockedUntil(string $domain): ?DateTimeImmutable
    {
        $row = $this->db->createCommand(
            'SELECT blocked_until FROM {{%source_block}} WHERE domain = :domain',
        )->bindValue(':domain', $domain)->queryOne();

        if ($row === false) {
            return null;
        }

        return new DateTimeImmutable($row['blocked_until']);
    }

    public function recordBlock(
        string $domain,
        DateTimeImmutable $blockedUntil,
        ?int $statusCode = null,
        ?string $error = null,
    ): void {
        $existing = $this->db->createCommand(
            'SELECT id, consecutive_count FROM {{%source_block}} WHERE domain = :domain',
        )->bindValue(':domain', $domain)->queryOne();

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existing === false) {
            $this->db->createCommand()->insert('{{%source_block}}', [
                'domain' => $domain,
                'blocked_at' => $now,
                'blocked_until' => $blockedUntil->format('Y-m-d H:i:s'),
                'consecutive_count' => 1,
                'last_status_code' => $statusCode,
                'last_error' => $error,
            ])->execute();
        } else {
            $this->db->createCommand()->update(
                '{{%source_block}}',
                [
                    'blocked_at' => $now,
                    'blocked_until' => $blockedUntil->format('Y-m-d H:i:s'),
                    'consecutive_count' => (int) $existing['consecutive_count'] + 1,
                    'last_status_code' => $statusCode,
                    'last_error' => $error,
                ],
                ['id' => $existing['id']],
            )->execute();
        }
    }

    public function getConsecutiveCount(string $domain): int
    {
        $row = $this->db->createCommand(
            'SELECT consecutive_count FROM {{%source_block}} WHERE domain = :domain',
        )->bindValue(':domain', $domain)->queryOne();

        return $row !== false ? (int) $row['consecutive_count'] : 0;
    }

    public function clearBlock(string $domain): void
    {
        $this->db->createCommand()->update(
            '{{%source_block}}',
            ['consecutive_count' => 0],
            ['domain' => $domain],
        )->execute();
    }

    public function cleanupExpired(): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        return $this->db->createCommand()->delete(
            '{{%source_block}}',
            'blocked_until < :now AND consecutive_count = 0',
            [':now' => $now],
        )->execute();
    }
}
