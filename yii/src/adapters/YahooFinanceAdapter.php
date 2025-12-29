<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Adapter for Yahoo Finance HTML pages and JSON API responses.
 */
final class YahooFinanceAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'yahoo_finance';

    private const SELECTORS = [
        'valuation.market_cap' => [
            'selector' => 'td[data-test="MARKET_CAP-value"]',
            'unit' => 'currency',
        ],
        'valuation.fwd_pe' => [
            'selector' => 'td[data-test="FORWARD_PE-value"]',
            'unit' => 'ratio',
        ],
        'valuation.trailing_pe' => [
            'selector' => 'td[data-test="PE_RATIO-value"]',
            'unit' => 'ratio',
        ],
        'valuation.ev_ebitda' => [
            'selector' => 'td[data-test="ENTERPRISE_VALUE_EBITDA-value"]',
            'unit' => 'ratio',
        ],
        'valuation.div_yield' => [
            'selector' => 'td[data-test="DIVIDEND_AND_YIELD-value"]',
            'unit' => 'percent',
        ],
        'valuation.free_cash_flow_ttm' => [
            'json_path' => '$.quoteSummary.result[0].financialData.freeCashflow',
            'unit' => 'currency',
        ],
        'valuation.price_to_book' => [
            'selector' => 'td[data-test="PB_RATIO-value"]',
            'unit' => 'ratio',
        ],
        'valuation.net_debt_ebitda' => [
            'json_path' => '$.quoteSummary.result[0].financialData.netDebtToEbitda',
            'unit' => 'ratio',
        ],
        'macro.commodity_benchmark' => [
            'json_path' => '$.quoteSummary.result[0].price.regularMarketPrice',
            'currency_path' => '$.quoteSummary.result[0].price.currency',
            'unit' => 'currency',
        ],
        'macro.margin_proxy' => [
            'json_path' => '$.quoteSummary.result[0].price.regularMarketPrice',
            'currency_path' => '$.quoteSummary.result[0].price.currency',
            'unit' => 'currency',
        ],
        'macro.sector_index' => [
            'json_path' => '$.quoteSummary.result[0].price.regularMarketPrice',
            'unit' => 'number',
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
        if ($request->fetchResult->isJson()) {
            return $this->adaptJson($request);
        }

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
        $extractions = [];
        $notFound = [];

        foreach ($request->datapointKeys as $key) {
            if (!isset(self::SELECTORS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::SELECTORS[$key];

            if (isset($config['selector'])) {
                $extraction = $this->extractByCssSelector($xpath, $key, $config);
                if ($extraction !== null) {
                    $extractions[$key] = $extraction;
                } else {
                    $notFound[] = $key;
                }
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
     * @param array<string, mixed> $config
     */
    private function extractByCssSelector(
        DOMXPath $xpath,
        string $key,
        array $config
    ): ?Extraction {
        $xpathQuery = $this->cssToXpath($config['selector']);
        $nodes = $xpath->query($xpathQuery);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if ($node === null) {
            return null;
        }

        $rawText = trim($node->textContent);

        if ($rawText === '' || $rawText === 'N/A' || $rawText === '--') {
            return null;
        }

        $parsed = $this->parseValue($rawText, $config['unit']);

        if ($parsed === null) {
            return null;
        }

        return new Extraction(
            datapointKey: $key,
            rawValue: $parsed['value'],
            unit: $config['unit'],
            currency: $parsed['currency'] ?? null,
            scale: $parsed['scale'] ?? null,
            asOf: null,
            locator: SourceLocator::html(
                $config['selector'],
                $this->getSnippetContext($node)
            ),
        );
    }

    /**
     * @return array{value: float, currency?: string, scale?: string}|null
     */
    private function parseValue(string $raw, string $unit): ?array
    {
        $raw = trim($raw);
        $normalized = str_replace(',', '', $raw);

        return match ($unit) {
            'currency' => $this->parseCurrencyValue($normalized),
            'ratio' => $this->parseRatioValue($normalized),
            'percent' => $this->parsePercentValue($normalized),
            'number' => $this->parseNumberValue($normalized),
            default => null,
        };
    }

    /**
     * @return array{value: float, scale: string, currency: string}|null
     */
    private function parseCurrencyValue(string $value): ?array
    {
        if (preg_match('/^([$€£])?([\d.]+)([TBMK])?$/i', $value, $matches)) {
            $symbol = $matches[1] ?? '';
            $number = (float)$matches[2];
            $suffix = strtoupper($matches[3] ?? '');

            $scale = match ($suffix) {
                'T' => 'trillions',
                'B' => 'billions',
                'M' => 'millions',
                'K' => 'thousands',
                default => 'units',
            };

            $currency = match ($symbol) {
                '€' => 'EUR',
                '£' => 'GBP',
                default => 'USD',
            };

            return [
                'value' => $number,
                'scale' => $scale,
                'currency' => $currency,
            ];
        }

        return null;
    }

    /**
     * @return array{value: float}|null
     */
    private function parseRatioValue(string $value): ?array
    {
        $value = rtrim($value, 'x');

        if (!is_numeric($value)) {
            return null;
        }

        return ['value' => (float)$value];
    }

    /**
     * @return array{value: float}|null
     */
    private function parsePercentValue(string $value): ?array
    {
        if (preg_match('/\\(([-\\d.]+)%\\)/', $value, $matches)) {
            return ['value' => (float)$matches[1]];
        }

        $value = rtrim($value, '%');

        if (!is_numeric($value)) {
            return null;
        }

        return ['value' => (float)$value];
    }

    /**
     * @return array{value: float}|null
     */
    private function parseNumberValue(string $value): ?array
    {
        if (!is_numeric($value)) {
            return null;
        }

        return ['value' => (float)$value];
    }

    private function cssToXpath(string $css): string
    {
        if (preg_match('/^(\w+)\[([a-z-]+)="([^"]+)"\]$/i', $css, $matches)) {
            return "//{$matches[1]}[@{$matches[2]}='{$matches[3]}']";
        }

        return "//{$css}";
    }

    private function getSnippetContext(DOMNode $node): string
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return trim($node->textContent);
        }

        $context = trim($parent->textContent);
        if (mb_strlen($context) > 100) {
            $context = mb_substr($context, 0, 97) . '...';
        }

        return $context;
    }

    private function adaptJson(AdaptRequest $request): AdaptResult
    {
        $data = json_decode($request->fetchResult->content, true);

        if ($data === null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Invalid JSON'
            );
        }

        $extractions = [];
        $notFound = [];

        foreach ($request->datapointKeys as $key) {
            if (!isset(self::SELECTORS[$key]['json_path'])) {
                $notFound[] = $key;
                continue;
            }

            $path = self::SELECTORS[$key]['json_path'];
            $rawValue = $this->getJsonPath($data, $path);
            $value = $this->normalizeJsonValue($rawValue);

            if ($value === null) {
                $notFound[] = $key;
                continue;
            }

            $unit = self::SELECTORS[$key]['unit'];
            $parsed = $this->parseJsonValue($value, $unit);

            if ($parsed === null) {
                $notFound[] = $key;
                continue;
            }

            $currency = null;
            $scale = null;
            if ($unit === 'currency') {
                $currency = $this->resolveCurrency($data, self::SELECTORS[$key]['currency_path'] ?? null) ?? 'USD';
                $scale = $parsed['scale'] ?? 'units';
            }

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $parsed['value'],
                unit: $unit,
                currency: $currency,
                scale: $scale,
                asOf: null,
                locator: SourceLocator::json($path, json_encode($rawValue) ?: ''),
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getJsonPath(array $data, string $path): string|int|float|array|bool|null
    {
        $path = ltrim($path, '$.');
        $keys = explode('.', $path);

        $current = $data;
        foreach ($keys as $key) {
            if (preg_match('/^(\w+)\[(\d+)\]$/', $key, $matches)) {
                if (!isset($current[$matches[1]][$matches[2]])) {
                    return null;
                }
                $current = $current[$matches[1]][$matches[2]];
            } else {
                if (!isset($current[$key])) {
                    return null;
                }
                $current = $current[$key];
            }
        }

        return $current;
    }

    private function normalizeJsonValue(string|int|float|array|bool|null $value): string|int|float|bool|null
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (array_key_exists('raw', $value)) {
                return is_scalar($value['raw']) ? $value['raw'] : null;
            }
            if (array_key_exists('fmt', $value)) {
                return is_scalar($value['fmt']) ? $value['fmt'] : null;
            }
            return null;
        }

        return $value;
    }

    /**
     * @return array{value: float, scale?: string}|null
     */
    private function parseJsonValue(string|int|float|bool $value, string $unit): ?array
    {
        if (is_bool($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return ['value' => (float)$value];
        }

        if (!is_string($value)) {
            return null;
        }

        return $this->parseValue($value, $unit);
    }

    private function resolveCurrency(array $data, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $value = $this->getJsonPath($data, $path);
        if (!is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }

        return $value;
    }
}
