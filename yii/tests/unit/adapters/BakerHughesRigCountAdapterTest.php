<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\BakerHughesRigCountAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\BakerHughesRigCountAdapter
 */
final class BakerHughesRigCountAdapterTest extends Unit
{
    public function testParsesRigCountFromXlsx(): void
    {
        $content = $this->loadFixture('baker-hughes/rig-count.xlsx');

        $adapter = new BakerHughesRigCountAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                statusCode: 200,
                url: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
                finalUrl: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['rig_count'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertArrayHasKey('rig_count', $result->extractions);

        $extraction = $result->extractions['rig_count'];
        $this->assertSame(545.0, $extraction->rawValue);
        $this->assertSame('number', $extraction->unit);
        $this->assertSame('2025-12-23', $extraction->asOf?->format('Y-m-d'));
    }

    public function testReturnsNotFoundForUnsupportedKey(): void
    {
        $content = $this->loadFixture('baker-hughes/rig-count.xlsx');

        $adapter = new BakerHughesRigCountAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                statusCode: 200,
                url: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
                finalUrl: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['unsupported_key'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['unsupported_key'], $result->notFound);
        $this->assertSame([], $result->extractions);
        $this->assertSame('Unsupported datapoint key', $result->parseError);
    }

    public function testReturnsNotFoundForMalformedXlsx(): void
    {
        $adapter = new BakerHughesRigCountAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: 'not a valid xlsx file',
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                statusCode: 200,
                url: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
                finalUrl: 'https://rigcount.bakerhughes.com/static-files/test.xlsx',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['rig_count'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['rig_count'], $result->notFound);
        $this->assertSame([], $result->extractions);
        $this->assertSame('Rig count not found in XLSX', $result->parseError);
    }

    private function loadFixture(string $path): string
    {
        $fullPath = dirname(__DIR__, 2) . '/fixtures/' . $path;
        $content = file_get_contents($fullPath);
        if ($content === false) {
            $this->fail('Failed to load fixture: ' . $fullPath);
        }

        return $content;
    }
}
