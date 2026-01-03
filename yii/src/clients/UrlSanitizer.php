<?php

declare(strict_types=1);

namespace app\clients;

/**
 * Redacts sensitive query parameters from URLs for safe logging/provenance.
 */
final class UrlSanitizer
{
    private const SENSITIVE_QUERY_KEYS = [
        'apikey',
        'api_key',
    ];

    public static function sanitize(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $queryParams = self::removeSensitiveParams($queryParams);
        }

        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        if ($host === '') {
            $query = self::buildQuery($queryParams);
            return $path . $query . $fragment;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $base = $scheme . '://' . $host . $port . $path;
        $query = self::buildQuery($queryParams);

        return $base . $query . $fragment;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    private static function removeSensitiveParams(array $queryParams): array
    {
        $filtered = [];
        foreach ($queryParams as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return in_array(strtolower($key), self::SENSITIVE_QUERY_KEYS, true);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private static function buildQuery(array $queryParams): string
    {
        if ($queryParams === []) {
            return '';
        }

        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $query === '' ? '' : '?' . $query;
    }
}
