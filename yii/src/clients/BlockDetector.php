<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\FetchResult;

/**
 * Detects soft blocks (CAPTCHA pages, JavaScript challenges) that return HTTP 200
 * but don't contain expected data.
 */
final class BlockDetector implements BlockDetectorInterface
{
    private const CAPTCHA_PATTERNS = [
        'g-recaptcha',
        'grecaptcha',
        'hcaptcha',
        'cf-turnstile',
        'captcha-container',
        'please verify you are a human',
        'prove you\'re not a robot',
        'complete the security check',
    ];

    private const JS_CHALLENGE_PATTERNS = [
        'cf-browser-verification',
        'cf_chl_prog',
        'challenge-platform',
        'just a moment...',
        'checking your browser',
        'please wait while we verify',
        '__cf_chl_rt_tk',
        'jschl-answer',
    ];

    private const RATE_LIMIT_PATTERNS = [
        'too many requests',
        'rate limit exceeded',
        'you have been rate limited',
        'slow down',
        'try again later',
    ];

    private const GEO_BLOCK_PATTERNS = [
        'not available in your region',
        'not available in your country',
        'geo-restricted',
        'access denied based on your location',
    ];

    private const LOGIN_REQUIRED_PATTERNS = [
        'sign in to continue',
        'login required',
        'please log in',
        'create an account',
    ];

    public function detect(FetchResult $result): BlockReason
    {
        if (!$result->isHtml()) {
            return BlockReason::None;
        }

        $content = strtolower($result->content);
        $contentLength = strlen($result->content);

        if ($contentLength < 1000 && $result->statusCode === 200) {
            if ($this->containsAny($content, self::CAPTCHA_PATTERNS)) {
                return BlockReason::Captcha;
            }
        }

        if ($this->containsAny($content, self::CAPTCHA_PATTERNS)) {
            return BlockReason::Captcha;
        }

        if ($this->containsAny($content, self::JS_CHALLENGE_PATTERNS)) {
            return BlockReason::JavaScriptChallenge;
        }

        if ($this->containsAny($content, self::RATE_LIMIT_PATTERNS)) {
            return BlockReason::RateLimitPage;
        }

        if ($this->containsAny($content, self::GEO_BLOCK_PATTERNS)) {
            return BlockReason::GeoBlocked;
        }

        if ($this->containsAny($content, self::LOGIN_REQUIRED_PATTERNS)) {
            return BlockReason::LoginRequired;
        }

        if ($result->statusCode === 503) {
            return BlockReason::ServiceUnavailable;
        }

        return BlockReason::None;
    }

    public function isRecoverable(BlockReason $reason): bool
    {
        return match ($reason) {
            BlockReason::None => true,
            BlockReason::Captcha => true,
            BlockReason::JavaScriptChallenge => false,
            BlockReason::RateLimitPage => true,
            BlockReason::GeoBlocked => false,
            BlockReason::LoginRequired => false,
            BlockReason::ServiceUnavailable => true,
        };
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
