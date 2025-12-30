<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use DateTimeImmutable;
use Exception;

/**
 * Adapter for EIA inventory API responses.
 */
final class EiaInventoryAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'eia_inventory';
    private const KEY_INVENTORY = 'inventory';
    private const KEY_OIL_INVENTORY = 'oil_inventory';
    private const SUPPORTED_KEYS = [
        self::KEY_INVENTORY,
        self::KEY_OIL_INVENTORY,
    ];

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
        $requestedKey = $this->resolveRequestedKey($request->datapointKeys);
        if ($requestedKey === null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Unsupported datapoint key',
            );
        }

        if (!$request->fetchResult->isJson()) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Unsupported content type',
            );
        }

        $decoded = json_decode($request->fetchResult->content, true);
        if (!is_array($decoded)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Invalid JSON',
            );
        }

        $data = $decoded['response']['data'] ?? null;
        if (!is_array($data) || $data === []) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Missing response data',
            );
        }

        $latest = $data[0];
        if (!is_array($latest)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Invalid response data',
            );
        }

        $valueRaw = $latest['value'] ?? null;
        if (!is_numeric($valueRaw)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Inventory value missing',
            );
        }

        $period = $latest['period'] ?? null;
        $asOf = null;
        if (is_string($period)) {
            try {
                $asOf = new DateTimeImmutable($period);
            } catch (Exception) {
                // Invalid date format, leave as null
            }
        }
        $units = is_string($latest['units'] ?? null) ? $latest['units'] : null;

        $extraction = new Extraction(
            datapointKey: $requestedKey,
            rawValue: (float) $valueRaw,
            unit: $units ?? 'number',
            currency: null,
            scale: null,
            asOf: $asOf,
            locator: SourceLocator::json(
                '$.response.data[0].value',
                sprintf('value=%s units=%s period=%s', $valueRaw, $units ?? 'unknown', $period ?? 'unknown')
            ),
        );

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: [$requestedKey => $extraction],
            notFound: [],
        );
    }

    /**
     * @param list<string> $datapointKeys
     */
    private function resolveRequestedKey(array $datapointKeys): ?string
    {
        foreach ($datapointKeys as $key) {
            if (in_array($key, self::SUPPORTED_KEYS, true)) {
                return $key;
            }
        }

        return null;
    }
}
