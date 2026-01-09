<?php

declare(strict_types=1);

namespace app\adapters;

use Psr\Http\Message\StreamInterface;

/**
 * Storage interface for PDF file persistence.
 */
interface StorageInterface
{
    /**
     * Store content and return the URI (local path or remote URL).
     */
    public function store(string $bytes, string $filename): string;

    /**
     * Delete a file by URI. Idempotent (no error if missing).
     */
    public function delete(string $uri): void;

    /**
     * Get a stream for reading the file.
     *
     * @throws \RuntimeException if file not found
     */
    public function stream(string $uri): StreamInterface;

    /**
     * Check if file exists.
     */
    public function exists(string $uri): bool;
}
