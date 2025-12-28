<?php

declare(strict_types=1);

namespace tests\unit\log;

use app\log\SanitizedFileTarget;
use Codeception\Test\Unit;
use yii\log\Logger;

/**
 * @covers \app\log\SanitizedFileTarget
 */
final class SanitizedFileTargetTest extends Unit
{
    public function testRedactsSensitiveHeaders(): void
    {
        $target = new SanitizedFileTarget();

        $message = [
            "Authorization: Bearer secret-token\nCookie: session=abc\nX-API-Key: 123456",
            Logger::LEVEL_INFO,
            'test',
            time(),
        ];

        $formatted = $target->formatMessage($message);

        $this->assertStringContainsString('Authorization: [REDACTED]', $formatted);
        $this->assertStringContainsString('Cookie: [REDACTED]', $formatted);
        $this->assertStringContainsString('X-API-Key: [REDACTED]', $formatted);
        $this->assertStringNotContainsString('secret-token', $formatted);
        $this->assertStringNotContainsString('session=abc', $formatted);
    }

    public function testRedactsSensitiveBodyPatterns(): void
    {
        $target = new SanitizedFileTarget();

        $message = [
            'api_key=abcdefghijklmnopqrstuvwxyz123456 '
            . 'Bearer abc.def-ghi '
            . 'session_id=abcdefghijklmnop '
            . 'user@example.com',
            Logger::LEVEL_INFO,
            'test',
            time(),
        ];

        $formatted = $target->formatMessage($message);

        $this->assertStringContainsString('[REDACTED]', $formatted);
        $this->assertStringContainsString('Bearer [REDACTED]', $formatted);
        $this->assertStringContainsString('[EMAIL_REDACTED]', $formatted);
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz123456', $formatted);
        $this->assertStringNotContainsString('abcdefghijklmnop', $formatted);
        $this->assertStringNotContainsString('user@example.com', $formatted);
    }

    public function testTruncatesLargeBodies(): void
    {
        $target = new SanitizedFileTarget();

        $html = '<html>' . str_repeat('a', 2000) . '</html>';
        $json = '{"data":"' . str_repeat('b', 3000) . '"}';

        $message = [
            $html . "\n" . $json,
            Logger::LEVEL_INFO,
            'test',
            time(),
        ];

        $formatted = $target->formatMessage($message);

        $this->assertStringContainsString('[TRUNCATED:', $formatted);
        $this->assertStringContainsString('[TRUNCATED JSON:', $formatted);
    }

    public function testLogVarsExcludeSensitiveGlobals(): void
    {
        $target = new SanitizedFileTarget();

        $this->assertNotContains('_SERVER', $target->logVars);
        $this->assertNotContains('_COOKIE', $target->logVars);
        $this->assertNotContains('_SESSION', $target->logVars);
        $this->assertNotContains('_ENV', $target->logVars);
    }
}
