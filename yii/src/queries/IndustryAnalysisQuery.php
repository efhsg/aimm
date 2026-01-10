<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\analysis\IndustryAnalysisContext;
use app\dto\MacroData;
use app\factories\CompanyDataDossierFactoryInterface;
use DateTimeImmutable;

/**
 * Query facade for building analysis context from dossier data.
 */
final class IndustryAnalysisQuery
{
    private const COMPANY_COLLECTED_AT_FIELDS = [
        'valuation_collected_at',
        'financials_collected_at',
        'quarters_collected_at',
    ];
    private const POLICY_COMMODITY_BENCHMARK = 'commodity_benchmark';
    private const POLICY_MARGIN_PROXY = 'margin_proxy';
    private const POLICY_SECTOR_INDEX = 'sector_index';
    private const POLICY_REQUIRED_INDICATORS = 'required_indicators';
    private const POLICY_OPTIONAL_INDICATORS = 'optional_indicators';
    private const PRICE_HISTORY_FIELDS = ['collected_at', 'price_date'];
    private const MACRO_INDICATOR_FIELDS = ['collected_at', 'indicator_date'];

    public function __construct(
        private readonly CompanyQuery $companyQuery,
        private readonly CompanyDataDossierFactoryInterface $companyFactory,
        private readonly MacroIndicatorQuery $macroQuery,
        private readonly PriceHistoryQuery $priceHistoryQuery,
    ) {
    }

    /**
     * Build analysis context from dossier data.
     *
     * @param array<string, mixed>|null $policy Collection policy for timestamp resolution
     */
    public function getForAnalysis(
        int $industryId,
        string $slug,
        ?array $policy = null
    ): IndustryAnalysisContext {
        $companyRows = $this->companyQuery->findByIndustry($industryId);
        $now = new DateTimeImmutable();
        $collectedAt = $this->resolveCollectedAt($companyRows, $policy) ?? $now;

        $companies = [];
        foreach ($companyRows as $row) {
            $companyData = $this->companyFactory->createFromDossier($row);
            if ($companyData !== null) {
                $companies[$row['ticker']] = $companyData;
            }
        }

        return new IndustryAnalysisContext(
            industryId: $industryId,
            industrySlug: $slug,
            collectedAt: $collectedAt,
            macro: new MacroData(),
            companies: $companies,
        );
    }

    /**
     * @param list<array<string, mixed>> $companyRows
     * @param array<string, mixed>|null $policy
     */
    private function resolveCollectedAt(array $companyRows, ?array $policy): ?DateTimeImmutable
    {
        $companyCollectedAt = $this->resolveCompanyCollectedAt($companyRows);
        $macroCollectedAt = $this->resolveMacroCollectedAt($policy);

        return $this->latestTimestamp($companyCollectedAt, $macroCollectedAt);
    }

    /**
     * @param list<array<string, mixed>> $companyRows
     */
    private function resolveCompanyCollectedAt(array $companyRows): ?DateTimeImmutable
    {
        $latest = null;

        foreach ($companyRows as $row) {
            foreach (self::COMPANY_COLLECTED_AT_FIELDS as $field) {
                $value = $row[$field] ?? null;
                if (!is_string($value) || $value === '') {
                    continue;
                }

                $timestamp = new DateTimeImmutable($value);
                if ($latest === null || $timestamp > $latest) {
                    $latest = $timestamp;
                }
            }
        }

        return $latest;
    }

    /**
     * @param array<string, mixed>|null $policy
     */
    private function resolveMacroCollectedAt(?array $policy): ?DateTimeImmutable
    {
        if ($policy === null) {
            return null;
        }

        $latest = null;
        $symbols = [
            $policy[self::POLICY_COMMODITY_BENCHMARK] ?? null,
            $policy[self::POLICY_MARGIN_PROXY] ?? null,
            $policy[self::POLICY_SECTOR_INDEX] ?? null,
        ];

        foreach ($symbols as $symbol) {
            if (!is_string($symbol) || $symbol === '') {
                continue;
            }

            $row = $this->priceHistoryQuery->findLatestBySymbol($symbol);
            $latest = $this->latestTimestamp(
                $latest,
                $this->extractTimestamp($row, self::PRICE_HISTORY_FIELDS)
            );
        }

        $indicators = array_merge(
            $this->normalizeIndicators($policy[self::POLICY_REQUIRED_INDICATORS] ?? null),
            $this->normalizeIndicators($policy[self::POLICY_OPTIONAL_INDICATORS] ?? null),
        );

        foreach (array_unique($indicators) as $indicatorKey) {
            $row = $this->macroQuery->findLatestByKey($indicatorKey);
            $latest = $this->latestTimestamp(
                $latest,
                $this->extractTimestamp($row, self::MACRO_INDICATOR_FIELDS)
            );
        }

        return $latest;
    }

    /**
     * @param array<string, mixed>|null $row
     * @param list<string> $fields
     */
    private function extractTimestamp(?array $row, array $fields): ?DateTimeImmutable
    {
        if ($row === null) {
            return null;
        }

        foreach ($fields as $field) {
            $value = $row[$field] ?? null;
            if (is_string($value) && $value !== '') {
                return new DateTimeImmutable($value);
            }
        }

        return null;
    }

    private function latestTimestamp(
        ?DateTimeImmutable $current,
        ?DateTimeImmutable $candidate
    ): ?DateTimeImmutable {
        if ($current === null) {
            return $candidate;
        }

        if ($candidate === null) {
            return $current;
        }

        return $candidate > $current ? $candidate : $current;
    }

    /**
     * @return list<string>
     */
    private function normalizeIndicators(mixed $value): array
    {
        $decoded = $this->decodeJson($value);
        if (!is_array($decoded)) {
            return [];
        }

        $keys = [];
        foreach ($decoded as $item) {
            if (is_string($item)) {
                $keys[] = $item;
                continue;
            }

            if (is_array($item) && isset($item['key']) && is_string($item['key'])) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }
}
