<?php

declare(strict_types=1);

namespace tests\unit\clients;

use app\alerts\AlertDispatcher;
use app\alerts\AlertNotifierInterface;
use app\alerts\CollectionAlertEvent;
use app\clients\AllowedDomainPolicyInterface;
use app\clients\BlockDetectorInterface;
use app\clients\BlockReason;
use app\clients\FetchRequest;
use app\clients\GuzzleWebFetchClient;
use app\clients\RateLimiterInterface;
use app\clients\UserAgentProviderInterface;
use app\exceptions\BlockedException;
use app\exceptions\NetworkException;
use app\exceptions\RateLimitException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use yii\log\Logger;

/**
 * @covers \app\clients\GuzzleWebFetchClient
 */
final class GuzzleWebFetchClientTest extends Unit
{
    public function testThrowsRateLimitExceptionAndCachesRetryUntilWhen429Seconds(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '120']),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->once())
            ->method('block')
            ->with(
                'finance.yahoo.com',
                $this->callback(static function (DateTimeImmutable $retryUntil): bool {
                    $expected = new DateTimeImmutable('+120 seconds');
                    $diff = abs($retryUntil->getTimestamp() - $expected->getTimestamp());
                    return $diff <= 3;
                })
            );

        $client = $this->createClient($mock, $rateLimiter);

        $this->expectException(RateLimitException::class);

        $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));
    }

    public function testThrowsRateLimitExceptionAndCachesRetryUntilWhen429HttpDate(): void
    {
        $retryHeader = 'Wed, 21 Oct 2015 07:28:00 GMT';
        $expectedRetry = new DateTimeImmutable($retryHeader);

        $mock = new MockHandler([
            new Response(429, ['Retry-After' => $retryHeader]),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->once())
            ->method('block')
            ->with('finance.yahoo.com', $expectedRetry);

        $client = $this->createClient($mock, $rateLimiter);

        $this->expectException(RateLimitException::class);

        $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));
    }

    public function testThrowsBlockedExceptionAndCooldownWhen403(): void
    {
        $mock = new MockHandler([
            new Response(403, [], 'Forbidden'),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->once())
            ->method('getConsecutiveBlockCount')
            ->with('finance.yahoo.com')
            ->willReturn(0);
        $rateLimiter->expects($this->once())
            ->method('recordBlock')
            ->with('finance.yahoo.com', $this->isInstanceOf(DateTimeImmutable::class));

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $client = $this->createClient($mock, $rateLimiter, $alertDispatcher);

        $this->expectException(BlockedException::class);

        $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));

        $this->assertCount(1, $alertNotifier->events);
        $this->assertSame('SOURCE_BLOCKED', $alertNotifier->events[0]->type);
    }

    public function testRetriesOnceOn5xxThenReturnsFetchResult(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'Error'),
            new Response(200, ['Content-Type' => 'text/html'], '<html>OK</html>'),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->exactly(2))
            ->method('wait')
            ->with('finance.yahoo.com');
        $rateLimiter->expects($this->exactly(2))
            ->method('recordAttempt')
            ->with('finance.yahoo.com');
        $rateLimiter->expects($this->once())
            ->method('recordSuccess')
            ->with('finance.yahoo.com');

        $client = $this->createClient($mock, $rateLimiter);
        $result = $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));

        $this->assertSame(200, $result->statusCode);
    }

    public function testRetriesOnceOnConnectExceptionThenReturnsFetchResult(): void
    {
        $request = new Request('GET', 'https://finance.yahoo.com/quote/AAPL');

        $mock = new MockHandler([
            new ConnectException('Connection failed', $request),
            new Response(200, ['Content-Type' => 'text/html'], '<html>OK</html>'),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->exactly(2))
            ->method('wait')
            ->with('finance.yahoo.com');
        $rateLimiter->expects($this->once())
            ->method('recordAttempt')
            ->with('finance.yahoo.com');
        $rateLimiter->expects($this->once())
            ->method('recordSuccess')
            ->with('finance.yahoo.com');

        $client = $this->createClient($mock, $rateLimiter);
        $result = $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));

        $this->assertSame(200, $result->statusCode);
    }

    public function testRetriesOnceOnConnectExceptionThenThrowsNetworkException(): void
    {
        $request = new Request('GET', 'https://finance.yahoo.com/quote/AAPL');

        $mock = new MockHandler([
            new ConnectException('Connection failed', $request),
            new ConnectException('Connection failed', $request),
            new ConnectException('Connection failed', $request),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->exactly(3))
            ->method('wait')
            ->with('finance.yahoo.com');

        $client = $this->createClient($mock, $rateLimiter);

        $this->expectException(NetworkException::class);

        $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));
    }

    public function testReturnsEffectiveFinalUrlAfterRedirect(): void
    {
        $mock = new MockHandler([
            new Response(302, ['Location' => 'https://finance.yahoo.com/quote/AAPL?p=AAPL']),
            new Response(200, ['Content-Type' => 'text/html'], '<html>OK</html>'),
        ]);

        $rateLimiter = $this->createRateLimiterMock();
        $rateLimiter->expects($this->once())
            ->method('recordSuccess')
            ->with('finance.yahoo.com');

        $client = $this->createClient($mock, $rateLimiter);

        $result = $client->fetch(new FetchRequest('https://finance.yahoo.com/quote/AAPL'));

        $this->assertSame('https://finance.yahoo.com/quote/AAPL?p=AAPL', $result->finalUrl);
        $this->assertTrue($result->wasRedirected());
    }

    private function createClient(
        MockHandler $mock,
        RateLimiterInterface $rateLimiter,
        ?AlertDispatcher $alertDispatcher = null
    ): GuzzleWebFetchClient {
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $userAgentProvider = $this->createMock(UserAgentProviderInterface::class);
        $userAgentProvider->method('getRandom')->willReturn('UnitTest');

        $blockDetector = $this->createMock(BlockDetectorInterface::class);
        $blockDetector->method('detect')->willReturn(BlockReason::None);
        $blockDetector->method('isRecoverable')->willReturn(true);

        $allowedDomainPolicy = $this->createMock(AllowedDomainPolicyInterface::class);
        $allowedDomainPolicy->method('assertAllowed');

        $alertDispatcher ??= new AlertDispatcher([new TestAlertNotifier()]);

        return new GuzzleWebFetchClient(
            httpClient: $client,
            rateLimiter: $rateLimiter,
            userAgentProvider: $userAgentProvider,
            blockDetector: $blockDetector,
            allowedDomainPolicy: $allowedDomainPolicy,
            alertDispatcher: $alertDispatcher,
            logger: new Logger(),
        );
    }

    private function createRateLimiterMock(): RateLimiterInterface
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('isRateLimited')->willReturn(false);
        $rateLimiter->method('getRetryTime')->willReturn(null);
        $rateLimiter->method('wait');
        $rateLimiter->method('recordAttempt');
        $rateLimiter->method('recordSuccess');
        $rateLimiter->method('recordBlock');
        $rateLimiter->method('getConsecutiveBlockCount')->willReturn(0);
        $rateLimiter->method('block');

        return $rateLimiter;
    }
}

final class TestAlertNotifier implements AlertNotifierInterface
{
    /**
     * @var CollectionAlertEvent[]
     */
    public array $events = [];

    public function notify(CollectionAlertEvent $event): void
    {
        $this->events[] = $event;
    }

    public function supports(string $severity): bool
    {
        return true;
    }
}
