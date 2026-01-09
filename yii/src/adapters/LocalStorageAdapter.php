<?php

declare(strict_types=1);

namespace app\adapters;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Local filesystem storage adapter for PDFs.
 *
 * Stores files in a configurable base path with automatic directory creation.
 */
final class LocalStorageAdapter implements StorageInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    public function store(string $bytes, string $filename): string
    {
        $path = $this->basePath . '/' . $filename;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = file_put_contents($path, $bytes);

        if ($written === false) {
            throw new RuntimeException("Failed to write file: {$path}");
        }

        return $path;
    }

    public function delete(string $uri): void
    {
        if (file_exists($uri)) {
            unlink($uri);
        }
    }

    public function stream(string $uri): StreamInterface
    {
        if (!file_exists($uri)) {
            throw new RuntimeException("File not found: {$uri}");
        }

        $handle = fopen($uri, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Failed to open file: {$uri}");
        }

        return Utils::streamFor($handle);
    }

    public function exists(string $uri): bool
    {
        return file_exists($uri);
    }
}
