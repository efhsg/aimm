<?php

declare(strict_types=1);

namespace app\clients;

use app\exceptions\NetworkException;
use Yii;

/**
 * Enforces domain allowlist and SSRF protection.
 *
 * Minimum enforcement rules:
 * - Scheme must be http or https
 * - Host must be in Yii::$app->params['allowedSourceDomains'] (exact match)
 * - Resolved IP must not be private/link-local/loopback (SSRF protection)
 */
final class AllowedDomainPolicy implements AllowedDomainPolicyInterface
{
    private const PRIVATE_IP_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '0.0.0.0/8',
    ];

    public function assertAllowed(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new NetworkException("Invalid URL format: {$url}", $url);
        }

        $scheme = $parsed['scheme'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new NetworkException("URL scheme must be http or https: {$url}", $url);
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            throw new NetworkException("URL missing host: {$url}", $url);
        }

        $allowedDomains = Yii::$app->params['allowedSourceDomains'] ?? [];
        if (!in_array($host, $allowedDomains, true)) {
            throw new NetworkException("Domain not in allowlist: {$host}", $url);
        }

        $ip = gethostbyname($host);
        if ($ip !== $host && $this->isPrivateIp($ip)) {
            throw new NetworkException("SSRF protection: {$host} resolves to private IP", $url);
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        foreach (self::PRIVATE_IP_RANGES as $range) {
            [$network, $bits] = explode('/', $range);
            $networkLong = ip2long($network);
            $mask = -1 << (32 - (int)$bits);

            if (($ipLong & $mask) === ($networkLong & $mask)) {
                return true;
            }
        }

        return false;
    }
}
