<?php

declare(strict_types=1);

namespace tests\unit\clients;

use app\clients\GotenbergClient;
use app\dto\pdf\PdfOptions;
use app\dto\pdf\RenderBundle;
use app\exceptions\GotenbergException;
use Codeception\Test\Unit;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * @covers \app\clients\GotenbergClient
 */
final class GotenbergClientTest extends Unit
{
    public function testReturnsPdfBodyOnSuccess(): void
    {
        $client = $this->createClient(new MockHandler([
            new Response(200, [], 'PDFDATA'),
        ]));

        $result = $client->render($this->createBundle(), new PdfOptions());

        $this->assertSame('PDFDATA', $result);
    }

    public function testThrowsNonRetryableExceptionOnClientError(): void
    {
        $client = $this->createClient(new MockHandler([
            new Response(400, [], 'Bad Request'),
        ]));

        try {
            $client->render($this->createBundle(), new PdfOptions());
            $this->fail('Expected exception was not thrown.');
        } catch (GotenbergException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame(400, $exception->statusCode);
            $this->assertSame('Bad Request', $exception->responseBodySnippet);
        }
    }

    public function testThrowsRetryableExceptionOnServerError(): void
    {
        $client = $this->createClient(new MockHandler([
            new Response(500, [], 'Server Error'),
        ]));

        try {
            $client->render($this->createBundle(), new PdfOptions());
            $this->fail('Expected exception was not thrown.');
        } catch (GotenbergException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(500, $exception->statusCode);
            $this->assertSame('Server Error', $exception->responseBodySnippet);
        }
    }

    public function testThrowsRetryableExceptionOnNetworkError(): void
    {
        $request = new Request('POST', 'http://example.com');
        $client = $this->createClient(new MockHandler([
            new ConnectException('Connection failed', $request),
        ]));

        try {
            $client->render($this->createBundle(), new PdfOptions());
            $this->fail('Expected exception was not thrown.');
        } catch (GotenbergException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertNull($exception->statusCode);
        }
    }

    private function createClient(MockHandler $mockHandler): GotenbergClient
    {
        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        return new GotenbergClient($httpClient, 'http://example.com');
    }

    private function createBundle(): RenderBundle
    {
        return RenderBundle::factory('trace')
            ->withIndexHtml('<html></html>')
            ->build();
    }
}
