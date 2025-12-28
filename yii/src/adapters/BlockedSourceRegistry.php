<?php

declare(strict_types=1);

namespace app\adapters;

use DateTimeImmutable;
use Yii;

/**
 * Tracks adapters that should be temporarily skipped due to blocks or repeated failures.
 * Persists blocked adapter state to @runtime/blocked-sources.json. Auto-expires entries.
 */
final class BlockedSourceRegistry
{
    private readonly string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? Yii::getAlias('@runtime/blocked-sources.json');
    }

    public function isBlocked(string $adapterId): bool
    {
        $blocked = $this->load();

        if (!isset($blocked[$adapterId])) {
            return false;
        }

        $until = new DateTimeImmutable($blocked[$adapterId]);
        return $until > new DateTimeImmutable();
    }

    public function block(string $adapterId, ?DateTimeImmutable $until = null): void
    {
        $until ??= new DateTimeImmutable('+6 hours');

        $blocked = $this->load();
        $blocked[$adapterId] = $until->format(DateTimeImmutable::ATOM);
        $this->save($blocked);
    }

    public function unblock(string $adapterId): void
    {
        $blocked = $this->load();
        unset($blocked[$adapterId]);
        $this->save($blocked);
    }

    public function getBlockedUntil(string $adapterId): ?DateTimeImmutable
    {
        $blocked = $this->load();

        if (!isset($blocked[$adapterId])) {
            return null;
        }

        return new DateTimeImmutable($blocked[$adapterId]);
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        if (!file_exists($this->storagePath)) {
            return [];
        }

        $content = file_get_contents($this->storagePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, string> $blocked
     */
    private function save(array $blocked): void
    {
        $now = new DateTimeImmutable();
        $blocked = array_filter($blocked, static function (string $until) use ($now): bool {
            return new DateTimeImmutable($until) > $now;
        });

        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->storagePath,
            json_encode($blocked, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
