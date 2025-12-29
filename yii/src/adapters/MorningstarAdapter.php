<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use DOMDocument;
use DOMXPath;

/**
 * Adapter for extracting financial data from Morningstar.
 */
final class MorningstarAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'morningstar';

    private const SELECTORS = [
        'valuation.market_cap' => [
            'selector' => '//*[@data-test="market-cap"]//text()',
            'unit' => 'currency',
        ],
        'valuation.trailing_pe' => [
            'selector' => '//*[@data-test="pe-ratio"]//text()',
            'unit' => 'ratio',
        ],
        'valuation.fwd_pe' => [
            'selector' => '//*[@data-test="forward-pe"]//text()',
            'unit' => 'ratio',
        ],
        'valuation.ev_ebitda' => [
            'selector' => '//*[@data-test="ev-ebitda"]//text()',
            'unit' => 'ratio',
        ],
        'valuation.div_yield' => [
            'selector' => '//*[@data-test="dividend-yield"]//text()',
            'unit' => 'percent',
        ],
        'valuation.price_to_book' => [
            'selector' => '//*[@data-test="price-book"]//text()',
            'unit' => 'ratio',
        ],
        'valuation.net_debt_ebitda' => [
            'selector' => '//*[@data-test="debt-equity"]//text()',
            'unit' => 'ratio',
        ],
    ];

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        return array_keys(self::SELECTORS);
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
        $extractions = [];
        $notFound = [];

        if (!$request->fetchResult->isHtml()) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Unsupported content type: ' . $request->fetchResult->contentType
            );
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($request->fetchResult->content, LIBXML_NOERROR);
        libxml_clear_errors();

        if (!$loaded) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Failed to parse HTML'
            );
        }

        $xpath = new DOMXPath($dom);

        foreach ($request->datapointKeys as $key) {
            if (!isset(self::SELECTORS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::SELECTORS[$key];
            $extraction = $this->extractByXpath($xpath, $key, $config);

            if ($extraction !== null) {
                $extractions[$key] = $extraction;
            } else {
                $notFound[] = $key;
            }
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
        );
    }

    /**
     * @param array<string, string> $config
     */
    private function extractByXpath(DOMXPath $xpath, string $key, array $config): ?Extraction
    {
        $selector = $config['selector'];
        $unit = $config['unit'];

        $nodes = $xpath->query($selector);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $rawValue = trim($nodes->item(0)->textContent);
        $parsed = $this->parseValue($rawValue, $unit);

        if ($parsed === null) {
            return null;
        }

        $currency = null;
        $scale = null;
        if ($unit === 'currency') {
            $currency = 'USD';
            $scale = $parsed['scale'] ?? 'units';
        }

        return new Extraction(
            datapointKey: $key,
            rawValue: $parsed['value'],
            unit: $unit,
            currency: $currency,
            scale: $scale,
            asOf: null,
            locator: SourceLocator::html($selector, $rawValue),
        );
    }

    /**
     * @return array{value: float, scale?: string}|null
     */
    private function parseValue(string $value, string $unit): ?array
    {
        $value = trim($value);

        // Handle ratio format "12.3x"
        if ($unit === 'ratio' && preg_match('/^([\d.]+)x$/i', $value, $matches)) {
            return ['value' => (float) $matches[1]];
        }

        // Handle negative percent format "(1.2%)"
        if ($unit === 'percent' && preg_match('/^\(([\d.]+)%?\)$/', $value, $matches)) {
            return ['value' => -1 * (float) $matches[1]];
        }

        $cleaned = preg_replace('/[,$%]/', '', $value);

        if ($cleaned === null || !is_numeric($cleaned)) {
            if (preg_match('/^([\d.]+)\s*(T|B|M|K|Bil|Mil)?$/i', trim($cleaned ?? $value), $matches)) {
                $number = (float) $matches[1];
                $suffix = strtoupper($matches[2] ?? '');

                $multiplier = match ($suffix) {
                    'T' => 1_000_000_000_000,
                    'B', 'BIL' => 1_000_000_000,
                    'M', 'MIL' => 1_000_000,
                    'K' => 1_000,
                    default => 1,
                };

                return [
                    'value' => $number * $multiplier,
                    'scale' => 'units',
                ];
            }

            return null;
        }

        return ['value' => (float) $cleaned];
    }
}
