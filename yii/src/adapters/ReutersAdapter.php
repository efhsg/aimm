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
 * Adapter for Reuters company pages.
 */
final class ReutersAdapter implements SourceAdapterInterface
{
    use ParsesFinancialValues;

    private const ADAPTER_ID = 'reuters';

    private const LABEL_MAP = [
        'valuation.market_cap' => [
            'labels' => ['market cap', 'market capitalization'],
            'unit' => 'currency',
        ],
        'valuation.fwd_pe' => [
            'labels' => ['forward p/e', 'forward pe'],
            'unit' => 'ratio',
        ],
        'valuation.trailing_pe' => [
            'labels' => ['p/e ratio', 'p/e', 'trailing p/e'],
            'unit' => 'ratio',
        ],
        'valuation.ev_ebitda' => [
            'labels' => ['ev/ebitda', 'ev / ebitda'],
            'unit' => 'ratio',
        ],
        'valuation.div_yield' => [
            'labels' => ['dividend yield'],
            'unit' => 'percent',
        ],
        'valuation.net_debt_ebitda' => [
            'labels' => ['net debt / ebitda', 'net debt/ebitda'],
            'unit' => 'ratio',
        ],
        'valuation.price_to_book' => [
            'labels' => ['price to book', 'p/b ratio', 'price/book'],
            'unit' => 'ratio',
        ],
    ];

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        return array_keys(self::LABEL_MAP);
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
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
            if (!isset(self::LABEL_MAP[$key])) {
                $notFound[] = $key;
                continue;
            }

            $extraction = $this->extractByLabels($xpath, $key, self::LABEL_MAP[$key]);
            if ($extraction === null) {
                $notFound[] = $key;
                continue;
            }

            $extractions[$key] = $extraction;
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
        );
    }

    /**
     * @param array{labels: list<string>, unit: string} $config
     */
    private function extractByLabels(DOMXPath $xpath, string $key, array $config): ?Extraction
    {
        $match = $this->findRowValue($xpath, $config['labels']);
        if ($match === null) {
            return null;
        }

        $parsed = $this->parseValue($match['value'], $config['unit']);
        if ($parsed === null) {
            return null;
        }

        $selector = $this->buildRowSelector($match['label']);

        return new Extraction(
            datapointKey: $key,
            rawValue: $parsed['value'],
            unit: $config['unit'],
            currency: $parsed['currency'] ?? null,
            scale: $parsed['scale'] ?? null,
            asOf: null,
            locator: SourceLocator::xpath(
                $selector,
                $this->getSnippetContext($match['node'])
            ),
        );
    }

    /**
     * @param list<string> $labels
     * @return array{label: string, value: string, node: DOMNode}|null
     */
    private function findRowValue(DOMXPath $xpath, array $labels): ?array
    {
        $normalizedLabels = array_map($this->normalizeLabel(...), $labels);
        $rows = $xpath->query('//tr');

        if ($rows === false) {
            return null;
        }

        foreach ($rows as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells === false || $cells->length < 2) {
                continue;
            }

            $labelNode = $cells->item(0);
            if ($labelNode === null) {
                continue;
            }

            $labelText = trim($labelNode->textContent);
            $label = $this->normalizeLabel($labelText);
            if (!in_array($label, $normalizedLabels, true)) {
                continue;
            }

            $valueNode = $cells->item($cells->length - 1);
            if ($valueNode === null) {
                continue;
            }

            $value = trim($valueNode->textContent);
            if ($value === '') {
                return null;
            }

            return [
                'label' => $labelText,
                'value' => $value,
                'node' => $valueNode,
            ];
        }

        return null;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        return rtrim($label, ':');
    }

    private function buildRowSelector(string $label): string
    {
        return sprintf("//tr[.//*[normalize-space()='%s']]", $label);
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
}
