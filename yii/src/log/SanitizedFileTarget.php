<?php

declare(strict_types=1);

namespace app\log;

use yii\log\FileTarget;

final class SanitizedFileTarget extends FileTarget
{
    private const REDACTED_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'proxy-authorization',
    ];

    private const REDACT_PATTERNS = [
        '/(["\']?(?:api[_-]?key|apikey|api_secret)["\']?\s*[:=]\s*["\']?)[a-zA-Z0-9_-]{20,}(["\']?)/i'
            => '$1[REDACTED]$2',
        '/Bearer\s+[a-zA-Z0-9._-]+/i'
            => 'Bearer [REDACTED]',
        '/(["\']?(?:session[_-]?id|sessionid|PHPSESSID|sid)["\']?\s*[:=]\s*["\']?)[a-zA-Z0-9_-]{16,}(["\']?)/i'
            => '$1[REDACTED]$2',
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i'
            => '[EMAIL_REDACTED]',
        '/\b\d{4}[- ]?\d{4}[- ]?\d{4}[- ]?\d{4}\b/'
            => '[CC_REDACTED]',
    ];

    private const MAX_BODY_LENGTH = 1024;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->logVars = array_diff(
            $this->logVars,
            ['_SERVER', '_COOKIE', '_SESSION', '_ENV']
        );
    }

    public function formatMessage($message): string
    {
        $formatted = parent::formatMessage($message);
        return $this->sanitize($formatted);
    }

    private function sanitize(string $message): string
    {
        foreach (self::REDACTED_HEADERS as $header) {
            $pattern = '/(' . preg_quote($header, '/') . '\s*[:=]\s*)[^\r\n]+/i';
            $message = preg_replace($pattern, '$1[REDACTED]', $message);
        }

        foreach (self::REDACT_PATTERNS as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return $this->truncateLargeBlocks($message);
    }

    private function truncateLargeBlocks(string $message): string
    {
        $pattern = '/(<html[^>]*>.*?<\/html>)/is';
        $message = preg_replace_callback($pattern, function (array $matches): string {
            $content = $matches[1];
            if (strlen($content) > self::MAX_BODY_LENGTH) {
                return substr($content, 0, self::MAX_BODY_LENGTH)
                    . "\n[TRUNCATED: " . strlen($content) . " bytes total]";
            }
            return $content;
        }, $message);

        $pattern = '/(\{(?:[^{}]|(?:\{[^{}]*\}))*\})/s';
        $message = preg_replace_callback($pattern, function (array $matches): string {
            $content = $matches[1];
            if (strlen($content) > self::MAX_BODY_LENGTH * 2) {
                return substr($content, 0, self::MAX_BODY_LENGTH)
                    . "\n[TRUNCATED JSON: " . strlen($content) . " bytes total]";
            }
            return $content;
        }, $message);

        return $message;
    }
}
