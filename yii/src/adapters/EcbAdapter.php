<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\HistoricalExtraction;
use app\dto\PeriodValue;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

/**
 * Adapter for European Central Bank (ECB) FX rate XML data.
 *
 * Parses the ECB historical reference rates XML to extract EUR/USD daily rates.
 * URL: https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml
 */
final class EcbAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'ecb';

    private const KEY_FX_RATES = 'macro.fx_rates';

    private const SUPPORTED_KEYS = [self::KEY_FX_RATES];

    private const ECB_NAMESPACE = 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref';
    private const GESMES_NAMESPACE = 'http://www.gesmes.org/xml/2002-08-01';

    private const TARGET_CURRENCY = 'USD';

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
        if (!in_array(self::KEY_FX_RATES, $request->datapointKeys, true)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
            );
        }

        if (!$this->isXmlContent($request->fetchResult->contentType)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'ECB adapter requires XML content, got: ' . $request->fetchResult->contentType,
            );
        }

        $historicalExtraction = $this->extractFxRates($request->fetchResult->content);

        if ($historicalExtraction === null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Failed to parse ECB FX rates XML',
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: [],
            notFound: [],
            historicalExtractions: [self::KEY_FX_RATES => $historicalExtraction],
        );
    }

    private function isXmlContent(string $contentType): bool
    {
        return str_contains($contentType, 'xml');
    }

    private function extractFxRates(string $content): ?HistoricalExtraction
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($content);
        libxml_clear_errors();

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('gesmes', self::GESMES_NAMESPACE);
        $xpath->registerNamespace('eurofxref', self::ECB_NAMESPACE);

        $periods = $this->parseDailyRates($xpath);

        if (empty($periods)) {
            return null;
        }

        return new HistoricalExtraction(
            datapointKey: self::KEY_FX_RATES,
            periods: $periods,
            unit: 'ratio',
            currency: 'EUR/' . self::TARGET_CURRENCY,
            scale: null,
            locator: SourceLocator::xpath(
                '//eurofxref:Cube[@currency="' . self::TARGET_CURRENCY . '"]',
                sprintf('EUR/%s: %d daily rates', self::TARGET_CURRENCY, count($periods))
            ),
        );
    }

    /**
     * Parse daily EUR/USD rates from ECB XML.
     *
     * @return list<PeriodValue>
     */
    private function parseDailyRates(DOMXPath $xpath): array
    {
        // Query all date cubes: <Cube time="YYYY-MM-DD">
        $dateCubes = $xpath->query('//eurofxref:Cube[@time]');

        if ($dateCubes === false || $dateCubes->length === 0) {
            return [];
        }

        $periods = [];

        foreach ($dateCubes as $dateCube) {
            $dateStr = $dateCube->getAttribute('time');
            $date = $this->parseDate($dateStr);

            if ($date === null) {
                continue;
            }

            // Query USD rate within this date cube
            $rateNodes = $xpath->query(
                sprintf('eurofxref:Cube[@currency="%s"]/@rate', self::TARGET_CURRENCY),
                $dateCube
            );

            if ($rateNodes === false || $rateNodes->length === 0) {
                continue;
            }

            $rateValue = $rateNodes->item(0)?->nodeValue;

            if ($rateValue === null || !is_numeric($rateValue)) {
                continue;
            }

            $periods[] = new PeriodValue(
                endDate: $date,
                value: (float) $rateValue,
            );
        }

        // Sort by date descending (newest first)
        usort(
            $periods,
            static fn (PeriodValue $a, PeriodValue $b): int =>
            $b->endDate->getTimestamp() <=> $a->endDate->getTimestamp()
        );

        return $periods;
    }

    private function parseDate(string $dateStr): ?DateTimeImmutable
    {
        if ($dateStr === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);

        return $date instanceof DateTimeImmutable ? $date : null;
    }
}
