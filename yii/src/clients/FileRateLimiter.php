<?php

declare(strict_types=1);

namespace app\clients;

use DateTimeImmutable;

/**
 * File-based rate limiter for development/single-process use.
 *
 * Note: Not safe for concurrent processes. Use DatabaseRateLimiter in production.
 */
final class FileRateLimiter implements RateLimiterInterface
{
    /**
     * Minimum delay between requests per domain (in milliseconds).
     */
    private const DOMAIN_DELAYS_MS = [
        'finance.yahoo.com' => 2000,
        'query1.finance.yahoo.com' => 2500,
        'www.reuters.com' => 3000,
        'financialmodelingprep.com' => 3000, // FMP free tier has 300 reqs/min = 200ms, but be conservative
        'default' => 1000,
    ];

    /** @var array<string, float> */
    private array $lastRequestTime = [];

    /** @var array<string, DateTimeImmutable> */
    private array $blockedUntil = [];

    /** @var array<string, int> */
    private array $consecutiveBlocks = [];

    public function __construct(
        private readonly string $storagePath,
    ) {
        $this->loadState();
    }

    public function isRateLimited(string $domain): bool
    {
        if (!isset($this->blockedUntil[$domain])) {
            return false;
        }

        if ($this->blockedUntil[$domain] <= new DateTimeImmutable()) {
            unset($this->blockedUntil[$domain]);
            $this->saveState();
            return false;
        }

        return true;
    }

    public function getRetryTime(string $domain): ?DateTimeImmutable
    {
        return $this->blockedUntil[$domain] ?? null;
    }

    public function wait(string $domain): void
    {
        $delayMs = self::DOMAIN_DELAYS_MS[$domain] ?? self::DOMAIN_DELAYS_MS['default'];
        $lastTime = $this->lastRequestTime[$domain] ?? 0;
        $now = microtime(true) * 1000;
        $elapsed = $now - $lastTime;

        if ($elapsed < $delayMs) {
            usleep((int)(($delayMs - $elapsed) * 1000));
        }
    }

    public function recordAttempt(string $domain): void
    {
        $this->lastRequestTime[$domain] = microtime(true) * 1000;
    }

    public function recordSuccess(string $domain): void
    {
        if (isset($this->consecutiveBlocks[$domain])) {
            $this->consecutiveBlocks[$domain] = 0;
            $this->saveState();
        }
    }

    public function recordBlock(string $domain, DateTimeImmutable $retryUntil): void
    {
        $this->consecutiveBlocks[$domain] = ($this->consecutiveBlocks[$domain] ?? 0) + 1;
        $this->blockedUntil[$domain] = $retryUntil;
        $this->saveState();
    }

    public function getConsecutiveBlockCount(string $domain): int
    {
        return $this->consecutiveBlocks[$domain] ?? 0;
    }

    public function block(string $domain, DateTimeImmutable $until): void
    {
        $this->blockedUntil[$domain] = $until;
        $this->saveState();
    }

    private function loadState(): void
    {
        $file = "{$this->storagePath}/ratelimit.json";
        if (!file_exists($file)) {
            return;
        }

        $handle = fopen($file, 'r');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return;
            }

            $content = stream_get_contents($handle);
            flock($handle, LOCK_UN);

            if ($content === false || $content === '') {
                return;
            }

            $data = json_decode($content, true);
            if ($data === null) {
                return;
            }

            foreach ($data['blockedUntil'] ?? [] as $domain => $timestamp) {
                $this->blockedUntil[$domain] = new DateTimeImmutable($timestamp);
            }

            $this->consecutiveBlocks = $data['consecutiveBlocks'] ?? [];
        } finally {
            fclose($handle);
        }
    }

    private function saveState(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $file = "{$this->storagePath}/ratelimit.json";
        $data = [
            'blockedUntil' => array_map(
                static fn (DateTimeImmutable $dt): string => $dt->format(DateTimeImmutable::ATOM),
                $this->blockedUntil
            ),
            'consecutiveBlocks' => $this->consecutiveBlocks,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);

        $tmpFile = "{$file}.tmp." . getmypid();
        if (file_put_contents($tmpFile, $json, LOCK_EX) === false) {
            @unlink($tmpFile);
            return;
        }

        chmod($tmpFile, 0600);

        if (!rename($tmpFile, $file)) {
            @unlink($tmpFile);
        }
    }
}
