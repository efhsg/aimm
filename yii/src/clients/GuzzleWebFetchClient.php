<?php

declare(strict_types=1);

namespace app\clients;

use app\alerts\AlertDispatcher;
use app\dto\FetchResult;
use app\exceptions\BlockedException;
use app\exceptions\NetworkException;
use app\exceptions\RateLimitException;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use yii\log\Logger;

/**
 * Guzzle-based implementation of WebFetchClientInterface.
 */
final class GuzzleWebFetchClient implements WebFetchClientInterface
{
    /**
     * Exponential backoff durations for 403 responses (in seconds).
     * After 4 consecutive 403s on same domain, use max duration.
     */
    private const BLOCK_BACKOFF_SECONDS = [
        0 => 300,
        1 => 900,
        2 => 3600,
        3 => 21600,
    ];

    public function __construct(
        private readonly Client $httpClient,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly UserAgentProviderInterface $userAgentProvider,
        private readonly BlockDetectorInterface $blockDetector,
        private readonly AllowedDomainPolicyInterface $allowedDomainPolicy,
        private readonly AlertDispatcher $alertDispatcher,
        private readonly Logger $logger,
    ) {
    }

    public function fetch(FetchRequest $request): FetchResult
    {
        $this->allowedDomainPolicy->assertAllowed($request->url);

        $safeRequestUrl = UrlSanitizer::sanitize($request->url);
        $domain = parse_url($request->url, PHP_URL_HOST);
        if (!is_string($domain) || $domain === '') {
            throw new NetworkException(
                "Invalid URL (missing host): {$safeRequestUrl}",
                $safeRequestUrl
            );
        }

        if ($this->rateLimiter->isRateLimited($domain)) {
            throw new RateLimitException(
                "Domain {$domain} is rate limited",
                $domain,
                $this->rateLimiter->getRetryTime($domain)
            );
        }

        $attempt = 0;
        $maxAttempts = 3;

        $allowRedirects = false;
        if ($request->followRedirects) {
            $allowRedirects = [
                'on_redirect' => function (
                    \Psr\Http\Message\RequestInterface $redirectRequest,
                    \Psr\Http\Message\ResponseInterface $redirectResponse,
                    \Psr\Http\Message\UriInterface $uri
                ): void {
                    $this->allowedDomainPolicy->assertAllowed((string)$uri);
                },
            ];
        }

        while (true) {
            $this->rateLimiter->wait($domain);
            $effectiveUrl = $request->url;
            $userAgent = $request->userAgent ?? $this->userAgentProvider->getRandom();

            try {
                $response = $this->httpClient->request('GET', $request->url, [
                    'headers' => array_merge(
                        ['User-Agent' => $userAgent],
                        $request->headers
                    ),
                    'timeout' => $request->timeoutSeconds,
                    'connect_timeout' => min(10, $request->timeoutSeconds),
                    'allow_redirects' => $allowRedirects,
                    'http_errors' => false,
                    'on_stats' => static function (TransferStats $stats) use (&$effectiveUrl): void {
                        $effectiveUrl = (string)$stats->getEffectiveUri();
                    },
                ]);

                $statusCode = $response->getStatusCode();
                $this->rateLimiter->recordAttempt($domain);
                $safeEffectiveUrl = UrlSanitizer::sanitize($effectiveUrl);

                if ($statusCode === 429) {
                    $retryUntil = $this->parseRetryAfter($response->getHeaderLine('Retry-After'))
                        ?? new DateTimeImmutable('+60 seconds');

                    $this->rateLimiter->block($domain, $retryUntil);
                    throw new RateLimitException(
                        "Rate limited by {$domain}",
                        $domain,
                        $retryUntil
                    );
                }

                if ($statusCode === 401 || $statusCode === 403) {
                    $consecutiveBlocks = $this->rateLimiter->getConsecutiveBlockCount($domain);
                    $backoffIndex = min($consecutiveBlocks, count(self::BLOCK_BACKOFF_SECONDS) - 1);
                    $backoffSeconds = self::BLOCK_BACKOFF_SECONDS[$backoffIndex];

                    $retryUntil = new DateTimeImmutable("+{$backoffSeconds} seconds");
                    $this->rateLimiter->recordBlock($domain, $retryUntil);

                    $this->alertDispatcher->alertBlocked($domain, $safeEffectiveUrl, $retryUntil);

                    throw new BlockedException(
                        "Forbidden by {$domain} (attempt {$consecutiveBlocks}, cooldown {$backoffSeconds}s)",
                        $domain,
                        $safeEffectiveUrl,
                        $retryUntil
                    );
                }

                if ($statusCode >= 500 && $statusCode <= 599 && $attempt < ($maxAttempts - 1)) {
                    $this->backoff($attempt);
                    $attempt++;
                    continue;
                }

                $fetchResult = new FetchResult(
                    content: (string)$response->getBody(),
                    contentType: $response->getHeader('Content-Type')[0] ?? 'text/html',
                    statusCode: $statusCode,
                    url: $safeRequestUrl,
                    finalUrl: $safeEffectiveUrl,
                    retrievedAt: new DateTimeImmutable(),
                    headers: $response->getHeaders(),
                );

                $this->allowedDomainPolicy->assertAllowed($fetchResult->finalUrl);

                $blockReason = $this->blockDetector->detect($fetchResult);

                if ($blockReason !== BlockReason::None) {
                    $this->logger->log(
                        [
                            'message' => 'Soft block detected',
                            'domain' => $domain,
                            'reason' => $blockReason->value,
                            'recoverable' => $this->blockDetector->isRecoverable($blockReason),
                        ],
                        Logger::LEVEL_WARNING,
                        'collection'
                    );

                    if ($blockReason === BlockReason::Captcha || $blockReason === BlockReason::RateLimitPage) {
                        $consecutiveBlocks = $this->rateLimiter->getConsecutiveBlockCount($domain);
                        $backoffIndex = min($consecutiveBlocks, count(self::BLOCK_BACKOFF_SECONDS) - 1);
                        $backoffSeconds = self::BLOCK_BACKOFF_SECONDS[$backoffIndex];

                        $retryUntil = new DateTimeImmutable("+{$backoffSeconds} seconds");
                        $this->rateLimiter->recordBlock($domain, $retryUntil);

                        $this->alertDispatcher->alertBlocked($domain, $safeEffectiveUrl, $retryUntil);

                        throw new BlockedException(
                            "Soft block ({$blockReason->value}) by {$domain}",
                            $domain,
                            $safeEffectiveUrl,
                            $retryUntil
                        );
                    }

                    if (!$this->blockDetector->isRecoverable($blockReason)) {
                        $this->alertDispatcher->alertBlocked($domain, $safeEffectiveUrl, null);

                        throw new BlockedException(
                            "Non-recoverable block ({$blockReason->value}) by {$domain}",
                            $domain,
                            $safeEffectiveUrl,
                            null
                        );
                    }
                }

                $this->rateLimiter->recordSuccess($domain);

                return $fetchResult;
            } catch (ConnectException | RequestException $e) {
                if ($attempt < ($maxAttempts - 1)) {
                    $this->backoff($attempt);
                    $attempt++;
                    continue;
                }

                throw new NetworkException(
                    "Request failed for {$safeRequestUrl}: {$e->getMessage()}",
                    $safeRequestUrl,
                    $e
                );
            }
        }
    }

    public function isRateLimited(string $domain): bool
    {
        return $this->rateLimiter->isRateLimited($domain);
    }

    private function backoff(int $attempt): void
    {
        $baseDelay = pow(2, $attempt);
        $jitter = rand(-100000, 100000) / 1_000_000;
        $delay = max(0.1, $baseDelay + $jitter);

        usleep((int)($delay * 1_000_000));
    }

    private function parseRetryAfter(string $headerValue): ?DateTimeImmutable
    {
        if ($headerValue === '') {
            return null;
        }
        if (ctype_digit($headerValue)) {
            return new DateTimeImmutable("+{$headerValue} seconds");
        }

        $date = DateTimeImmutable::createFromFormat(DATE_RFC1123, $headerValue);
        return $date ?: null;
    }
}
