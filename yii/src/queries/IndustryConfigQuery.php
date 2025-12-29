<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\CompanyConfig;
use app\dto\DataRequirements;
use app\dto\IndustryConfig as IndustryConfigDto;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\models\IndustryConfig as IndustryConfigRecord;
use app\validators\SchemaValidatorInterface;
use JsonException;
use RuntimeException;

final class IndustryConfigQuery
{
    private const SCHEMA_FILE = 'industry-config.schema.json';

    private const KEY_ID = 'id';
    private const KEY_NAME = 'name';
    private const KEY_SECTOR = 'sector';
    private const KEY_COMPANIES = 'companies';
    private const KEY_MACRO_REQUIREMENTS = 'macro_requirements';
    private const KEY_DATA_REQUIREMENTS = 'data_requirements';

    private const KEY_TICKER = 'ticker';
    private const KEY_LISTING_EXCHANGE = 'listing_exchange';
    private const KEY_LISTING_CURRENCY = 'listing_currency';
    private const KEY_REPORTING_CURRENCY = 'reporting_currency';
    private const KEY_FY_END_MONTH = 'fy_end_month';
    private const KEY_ALTERNATIVE_TICKERS = 'alternative_tickers';

    private const KEY_COMMODITY_BENCHMARK = 'commodity_benchmark';
    private const KEY_MARGIN_PROXY = 'margin_proxy';
    private const KEY_SECTOR_INDEX = 'sector_index';
    private const KEY_REQUIRED_INDICATORS = 'required_indicators';
    private const KEY_OPTIONAL_INDICATORS = 'optional_indicators';

    private const KEY_HISTORY_YEARS = 'history_years';
    private const KEY_QUARTERS_TO_FETCH = 'quarters_to_fetch';
    private const KEY_VALUATION_METRICS = 'valuation_metrics';
    private const KEY_ANNUAL_FINANCIAL_METRICS = 'annual_financial_metrics';
    private const KEY_QUARTER_METRICS = 'quarter_metrics';
    private const KEY_OPERATIONAL_METRICS = 'operational_metrics';

    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
    ) {
    }

    public function findById(string $id): ?IndustryConfigDto
    {
        $record = IndustryConfigRecord::find()
            ->byIndustryId($id)
            ->one();

        if ($record === null) {
            return null;
        }

        return $this->buildDto($record);
    }

    /**
     * @return list<IndustryConfigDto>
     */
    public function findAllActive(): array
    {
        $records = IndustryConfigRecord::find()
            ->active()
            ->all();

        $configs = [];
        foreach ($records as $record) {
            $configs[] = $this->buildDto($record);
        }

        return $configs;
    }

    private function buildDto(IndustryConfigRecord $record): IndustryConfigDto
    {
        $data = $this->validateAndDecode($record);

        return $this->mapIndustryConfig($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAndDecode(IndustryConfigRecord $record): array
    {
        $result = $this->schemaValidator->validate(
            $record->config_yaml,
            self::SCHEMA_FILE
        );

        if (!$result->isValid()) {
            throw new RuntimeException(
                "Industry config {$record->industry_id} failed schema validation: "
                . implode(', ', $result->getErrors())
            );
        }

        try {
            $data = json_decode(
                $record->config_yaml,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Industry config {$record->industry_id} JSON decode failed: {$exception->getMessage()}",
                0,
                $exception
            );
        }

        if (!is_array($data)) {
            throw new RuntimeException(
                "Industry config {$record->industry_id} JSON must decode to object"
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapIndustryConfig(array $data): IndustryConfigDto
    {
        $companies = $this->mapCompanies($data[self::KEY_COMPANIES]);
        $macroRequirements = $this->mapMacroRequirements($data[self::KEY_MACRO_REQUIREMENTS]);
        $dataRequirements = $this->mapDataRequirements($data[self::KEY_DATA_REQUIREMENTS]);

        return new IndustryConfigDto(
            id: $data[self::KEY_ID],
            name: $data[self::KEY_NAME],
            sector: $data[self::KEY_SECTOR],
            companies: $companies,
            macroRequirements: $macroRequirements,
            dataRequirements: $dataRequirements,
        );
    }

    /**
     * @param list<array<string, mixed>> $companies
     * @return list<CompanyConfig>
     */
    private function mapCompanies(array $companies): array
    {
        return array_map(
            fn (array $company): CompanyConfig => $this->mapCompanyConfig($company),
            $companies
        );
    }

    /**
     * @param array<string, mixed> $company
     */
    private function mapCompanyConfig(array $company): CompanyConfig
    {
        $alternativeTickers = $company[self::KEY_ALTERNATIVE_TICKERS] ?? null;

        return new CompanyConfig(
            ticker: $company[self::KEY_TICKER],
            name: $company[self::KEY_NAME],
            listingExchange: $company[self::KEY_LISTING_EXCHANGE],
            listingCurrency: $company[self::KEY_LISTING_CURRENCY],
            reportingCurrency: $company[self::KEY_REPORTING_CURRENCY],
            fyEndMonth: $company[self::KEY_FY_END_MONTH],
            alternativeTickers: $alternativeTickers,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapMacroRequirements(array $data): MacroRequirements
    {
        return new MacroRequirements(
            commodityBenchmark: $data[self::KEY_COMMODITY_BENCHMARK] ?? null,
            marginProxy: $data[self::KEY_MARGIN_PROXY] ?? null,
            sectorIndex: $data[self::KEY_SECTOR_INDEX] ?? null,
            requiredIndicators: $data[self::KEY_REQUIRED_INDICATORS] ?? [],
            optionalIndicators: $data[self::KEY_OPTIONAL_INDICATORS] ?? [],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapDataRequirements(array $data): DataRequirements
    {
        return new DataRequirements(
            historyYears: $data[self::KEY_HISTORY_YEARS],
            quartersToFetch: $data[self::KEY_QUARTERS_TO_FETCH],
            valuationMetrics: $this->mapMetricDefinitions($data[self::KEY_VALUATION_METRICS] ?? []),
            annualFinancialMetrics: $this->mapMetricDefinitions($data[self::KEY_ANNUAL_FINANCIAL_METRICS] ?? []),
            quarterMetrics: $this->mapMetricDefinitions($data[self::KEY_QUARTER_METRICS] ?? []),
            operationalMetrics: $this->mapMetricDefinitions($data[self::KEY_OPERATIONAL_METRICS] ?? []),
        );
    }

    /**
     * @param list<array<string, mixed>> $metrics
     * @return list<MetricDefinition>
     */
    private function mapMetricDefinitions(array $metrics): array
    {
        return array_map(
            static fn (array $metric): MetricDefinition => new MetricDefinition(
                key: $metric['key'],
                unit: $metric['unit'],
                required: $metric['required'] ?? false,
            ),
            $metrics
        );
    }
}
