<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\CollectionLog;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\IndustryDataPack;
use app\dto\MacroData;
use app\enums\CollectionStatus;
use DateTimeImmutable;

/**
 * Factory for reconstructing IndustryDataPack from arrays.
 */
final class IndustryDataPackFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): IndustryDataPack
    {
        $companies = [];
        foreach ($data['companies'] as $ticker => $companyData) {
            $companies[$ticker] = CompanyDataFactory::fromArray($companyData);
        }

        return new IndustryDataPack(
            industryId: $data['industry_id'],
            datapackId: $data['datapack_id'],
            collectedAt: new DateTimeImmutable($data['collected_at']),
            macro: self::macroFromArray($data['macro']),
            companies: $companies,
            collectionLog: self::collectionLogFromArray($data['collection_log']),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function macroFromArray(array $data): MacroData
    {
        $additionalIndicators = [];
        foreach ($data['additional_indicators'] ?? [] as $key => $indicator) {
            $additionalIndicators[$key] = self::macroDataPointFromArray($indicator);
        }

        return new MacroData(
            commodityBenchmark: isset($data['commodity_benchmark'])
                ? CompanyDataFactory::moneyFromArray($data['commodity_benchmark'])
                : null,
            marginProxy: isset($data['margin_proxy'])
                ? CompanyDataFactory::moneyFromArray($data['margin_proxy'])
                : null,
            sectorIndex: isset($data['sector_index'])
                ? CompanyDataFactory::numberFromArray($data['sector_index'])
                : null,
            additionalIndicators: $additionalIndicators,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function macroDataPointFromArray(array $data): DataPointMoney|DataPointNumber
    {
        return match ($data['unit']) {
            'currency' => CompanyDataFactory::moneyFromArray($data),
            default => CompanyDataFactory::numberFromArray($data),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function collectionLogFromArray(array $data): CollectionLog
    {
        $companyStatuses = [];
        foreach ($data['company_statuses'] as $ticker => $status) {
            $companyStatuses[$ticker] = CollectionStatus::from($status);
        }

        return new CollectionLog(
            startedAt: new DateTimeImmutable($data['started_at']),
            completedAt: new DateTimeImmutable($data['completed_at']),
            durationSeconds: $data['duration_seconds'],
            companyStatuses: $companyStatuses,
            macroStatus: CollectionStatus::from($data['macro_status']),
            totalAttempts: $data['total_attempts'],
        );
    }
}
