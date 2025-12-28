<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\AdapterChain;
use app\adapters\BlockedSourceRegistry;
use app\adapters\SourceAdapterInterface;
use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\exceptions\BlockedException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Exception;
use yii\log\Logger;

final class AdapterChainTest extends Unit
{
    private BlockedSourceRegistry $blockedRegistry;
    private Logger $logger;
    private string $tempStoragePath;

    protected function _before(): void
    {
        $this->tempStoragePath = sys_get_temp_dir() . '/blocked-sources-test-' . uniqid() . '.json';
        $this->blockedRegistry = new BlockedSourceRegistry($this->tempStoragePath);
        $this->logger = $this->createMock(Logger::class);
    }

    protected function _after(): void
    {
        if (file_exists($this->tempStoragePath)) {
            unlink($this->tempStoragePath);
        }
    }

    public function testReturnsChainAsAdapterId(): void
    {
        $chain = new AdapterChain([], $this->blockedRegistry, $this->logger);

        $this->assertSame('chain', $chain->getAdapterId());
    }

    public function testReturnsMergedSupportedKeysFromAllAdapters(): void
    {
        $adapter1 = $this->createStubAdapter('adapter1', ['key1', 'key2']);
        $adapter2 = $this->createStubAdapter('adapter2', ['key2', 'key3']);

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $keys = $chain->getSupportedKeys();

        $this->assertCount(3, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
    }

    public function testExtractsFromFirstAdapterThatSucceeds(): void
    {
        $request = $this->createRequest(['pe_ratio']);

        $adapter1 = $this->createStubAdapter('adapter1', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter1',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: null,
        ));

        $adapter2 = $this->createStubAdapter('adapter2', ['pe_ratio']);

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertArrayHasKey('pe_ratio', $result->extractions);
        $this->assertEmpty($result->notFound);
    }

    public function testFallsBackToSecondAdapterWhenFirstFails(): void
    {
        $request = $this->createRequest(['pe_ratio']);

        $adapter1 = $this->createThrowingAdapter('adapter1', ['pe_ratio'], new Exception('Connection failed'));

        $adapter2 = $this->createStubAdapter('adapter2', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter2',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: null,
        ));

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertArrayHasKey('pe_ratio', $result->extractions);
        $this->assertStringContainsString('[adapter1] Error:', $result->parseError);
    }

    public function testSkipsBlockedAdapters(): void
    {
        $request = $this->createRequest(['pe_ratio']);

        $adapter1 = $this->createStubAdapter('adapter1', ['pe_ratio']);

        $adapter2 = $this->createStubAdapter('adapter2', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter2',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: null,
        ));

        $this->blockedRegistry->block('adapter1', new DateTimeImmutable('+1 hour'));

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertArrayHasKey('pe_ratio', $result->extractions);
    }

    public function testBlocksAdapterOnBlockedException(): void
    {
        $request = $this->createRequest(['pe_ratio']);

        $adapter1 = $this->createThrowingAdapter('adapter1', ['pe_ratio'], new BlockedException(
            message: 'Rate limited',
            domain: 'example.com',
            url: 'https://example.com/quote',
            retryAfter: new DateTimeImmutable('+1 hour'),
        ));

        $adapter2 = $this->createStubAdapter('adapter2', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter2',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: null,
        ));

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertTrue($this->blockedRegistry->isBlocked('adapter1'));
        $this->assertArrayHasKey('pe_ratio', $result->extractions);
    }

    public function testMergesPartialResultsFromMultipleAdapters(): void
    {
        $request = $this->createRequest(['pe_ratio', 'market_cap']);

        $adapter1 = $this->createStubAdapter('adapter1', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter1',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: null,
        ));

        $adapter2 = $this->createStubAdapter('adapter2', ['market_cap'], new AdaptResult(
            adapterId: 'adapter2',
            extractions: [
                'market_cap' => $this->createExtraction('market_cap', '3.01T'),
            ],
            notFound: [],
            parseError: null,
        ));

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertArrayHasKey('pe_ratio', $result->extractions);
        $this->assertArrayHasKey('market_cap', $result->extractions);
        $this->assertEmpty($result->notFound);
    }

    public function testSkipsAdapterThatDoesNotSupportRemainingKeys(): void
    {
        $request = $this->createRequest(['pe_ratio']);

        $adapter1 = $this->createStubAdapter('adapter1', ['market_cap']);

        $adapter2 = $this->createStubAdapter('adapter2', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter2',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: null,
        ));

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertArrayHasKey('pe_ratio', $result->extractions);
    }

    public function testReturnsNotFoundWhenNoAdapterCanExtract(): void
    {
        $request = $this->createRequest(['unknown_key']);

        $adapter1 = $this->createStubAdapter('adapter1', ['pe_ratio']);

        $chain = new AdapterChain([$adapter1], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertEmpty($result->extractions);
        $this->assertContains('unknown_key', $result->notFound);
    }

    public function testCollectsParseErrorsFromMultipleAdapters(): void
    {
        $request = $this->createRequest(['pe_ratio', 'market_cap']);

        $adapter1 = $this->createStubAdapter('adapter1', ['pe_ratio'], new AdaptResult(
            adapterId: 'adapter1',
            extractions: [
                'pe_ratio' => $this->createExtraction('pe_ratio', '25.5'),
            ],
            notFound: [],
            parseError: 'Warning: some data missing',
        ));

        $adapter2 = $this->createStubAdapter('adapter2', ['market_cap'], new AdaptResult(
            adapterId: 'adapter2',
            extractions: [
                'market_cap' => $this->createExtraction('market_cap', '3.01T'),
            ],
            notFound: [],
            parseError: 'Warning: format changed',
        ));

        $chain = new AdapterChain([$adapter1, $adapter2], $this->blockedRegistry, $this->logger);

        $result = $chain->adapt($request);

        $this->assertStringContainsString('[adapter1] Warning: some data missing', $result->parseError);
        $this->assertStringContainsString('[adapter2] Warning: format changed', $result->parseError);
    }

    /**
     * @param string[] $supportedKeys
     */
    private function createStubAdapter(
        string $adapterId,
        array $supportedKeys,
        ?AdaptResult $result = null,
    ): SourceAdapterInterface {
        return new class ($adapterId, $supportedKeys, $result) implements SourceAdapterInterface {
            public function __construct(
                private readonly string $adapterId,
                private readonly array $supportedKeys,
                private readonly ?AdaptResult $result,
            ) {
            }

            public function getAdapterId(): string
            {
                return $this->adapterId;
            }

            public function getSupportedKeys(): array
            {
                return $this->supportedKeys;
            }

            public function adapt(AdaptRequest $request): AdaptResult
            {
                if ($this->result === null) {
                    return new AdaptResult(
                        adapterId: $this->adapterId,
                        extractions: [],
                        notFound: $request->datapointKeys,
                        parseError: null,
                    );
                }

                return $this->result;
            }
        };
    }

    /**
     * @param string[] $supportedKeys
     */
    private function createThrowingAdapter(
        string $adapterId,
        array $supportedKeys,
        \Throwable $exception,
    ): SourceAdapterInterface {
        return new class ($adapterId, $supportedKeys, $exception) implements SourceAdapterInterface {
            public function __construct(
                private readonly string $adapterId,
                private readonly array $supportedKeys,
                private readonly \Throwable $exception,
            ) {
            }

            public function getAdapterId(): string
            {
                return $this->adapterId;
            }

            public function getSupportedKeys(): array
            {
                return $this->supportedKeys;
            }

            public function adapt(AdaptRequest $request): AdaptResult
            {
                throw $this->exception;
            }
        };
    }

    /**
     * @param string[] $datapointKeys
     */
    private function createRequest(array $datapointKeys): AdaptRequest
    {
        $fetchResult = new FetchResult(
            content: '<html></html>',
            contentType: 'text/html',
            statusCode: 200,
            url: 'https://example.com',
            finalUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
        );

        return new AdaptRequest(
            fetchResult: $fetchResult,
            datapointKeys: $datapointKeys,
            ticker: 'AAPL',
        );
    }

    private function createExtraction(string $datapointKey, string $rawValue): Extraction
    {
        return new Extraction(
            datapointKey: $datapointKey,
            rawValue: $rawValue,
            unit: 'number',
            currency: null,
            scale: null,
            asOf: null,
            locator: SourceLocator::html('td[data-test="PE_RATIO-value"]', $rawValue),
        );
    }
}
