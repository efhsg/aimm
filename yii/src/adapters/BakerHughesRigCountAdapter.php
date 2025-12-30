<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Adapter for Baker Hughes rig count XLSX reports.
 */
final class BakerHughesRigCountAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'baker_hughes_rig_count';
    private const KEY_RIG_COUNT = 'rig_count';
    private const SUPPORTED_KEYS = [self::KEY_RIG_COUNT];

    private const XML_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const SHEET_PATH = 'xl/worksheets/sheet1.xml';
    private const SHARED_STRINGS_PATH = 'xl/sharedStrings.xml';

    private const DATE_CELL = 'D4';
    private const LABEL_COLUMN = 'B';
    private const VALUE_COLUMN = 'D';
    private const US_TOTAL_LABEL = 'United States Total';

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
        if (!in_array(self::KEY_RIG_COUNT, $request->datapointKeys, true)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Unsupported datapoint key',
            );
        }

        $extraction = $this->extractRigCount($request->fetchResult->content);

        if ($extraction === null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Rig count not found in XLSX',
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: [self::KEY_RIG_COUNT => $extraction],
            notFound: [],
        );
    }

    private function extractRigCount(string $content): ?Extraction
    {
        $parts = $this->readWorkbookParts($content);
        if ($parts === null) {
            return null;
        }

        $sharedStrings = $this->parseSharedStrings($parts['shared']);
        $cells = $this->parseCellMap($parts['sheet'], $sharedStrings);

        $asOf = $this->parseExcelDate($cells[self::DATE_CELL] ?? null);
        $row = $this->findLabelRow($cells, self::US_TOTAL_LABEL);

        if ($row === null) {
            return null;
        }

        $valueCell = self::VALUE_COLUMN . $row;
        $valueRaw = $cells[$valueCell] ?? null;

        if (!is_numeric($valueRaw)) {
            return null;
        }

        $value = (float) $valueRaw;

        return new Extraction(
            datapointKey: self::KEY_RIG_COUNT,
            rawValue: $value,
            unit: 'number',
            currency: null,
            scale: null,
            asOf: $asOf,
            locator: SourceLocator::xpath(
                sprintf('//x:row[@r="%s"]/x:c[@r="%s"]', $row, $valueCell),
                sprintf('%s %s=%s', self::US_TOTAL_LABEL, $valueCell, $valueRaw)
            ),
        );
    }

    /**
     * @return array{sheet: string, shared: string}|null
     */
    private function readWorkbookParts(string $content): ?array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'rigcount_');
        if ($tempPath === false) {
            return null;
        }

        try {
            if (file_put_contents($tempPath, $content) === false) {
                return null;
            }

            $zip = new ZipArchive();
            if ($zip->open($tempPath) !== true) {
                return null;
            }

            $sheet = $zip->getFromName(self::SHEET_PATH);
            $shared = $zip->getFromName(self::SHARED_STRINGS_PATH);
            $zip->close();

            if ($sheet === false || $shared === false) {
                return null;
            }

            return [
                'sheet' => $sheet,
                'shared' => $shared,
            ];
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(string $xml): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('x', self::XML_NS);

        $strings = [];
        $nodes = $xpath->query('//x:si');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $textNodes = $xpath->query('.//x:t', $node);
            if ($textNodes === false) {
                continue;
            }

            $text = '';
            foreach ($textNodes as $textNode) {
                $text .= $textNode->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param list<string> $sharedStrings
     * @return array<string, string>
     */
    private function parseCellMap(string $xml, array $sharedStrings): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('x', self::XML_NS);

        $cells = [];
        $nodes = $xpath->query('//x:c');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $cell) {
            $ref = $cell->getAttribute('r');
            if ($ref === '') {
                continue;
            }

            $valueNode = $xpath->query('x:v', $cell)->item(0);
            if ($valueNode === null) {
                continue;
            }

            $value = $valueNode->textContent;
            $cellType = $cell->getAttribute('t');
            if ($cellType === 's') {
                $index = (int) $value;
                $value = $sharedStrings[$index] ?? '';
            }

            $cells[$ref] = $value;
        }

        return $cells;
    }

    /**
     * @param array<string, string> $cells
     */
    private function findLabelRow(array $cells, string $label): ?string
    {
        foreach ($cells as $ref => $value) {
            if ($value !== $label) {
                continue;
            }

            if (!str_starts_with($ref, self::LABEL_COLUMN)) {
                continue;
            }

            return substr($ref, 1);
        }

        return null;
    }

    private function parseExcelDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_numeric($value)) {
            return null;
        }

        $days = (int) floor((float) $value);
        $baseDate = new DateTimeImmutable('1899-12-30');

        return $baseDate->modify(sprintf('+%d days', $days));
    }
}
