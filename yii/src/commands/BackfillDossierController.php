<?php

declare(strict_types=1);

namespace app\commands;

use app\queries\AnnualFinancialQuery;
use app\queries\CompanyQuery;
use app\queries\QuarterlyFinancialQuery;
use app\queries\ValuationSnapshotQuery;
use DateTimeImmutable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * One-time migration script to backfill dossier from Phase 1 datapacks.
 */
final class BackfillDossierController extends Controller
{
    private const DATAPACK_DIR = '@runtime/datapacks';

    public function __construct(
        $id,
        $module,
        private readonly CompanyQuery $companyQuery,
        private readonly AnnualFinancialQuery $annualQuery,
        private readonly QuarterlyFinancialQuery $quarterlyQuery,
        private readonly ValuationSnapshotQuery $valuationQuery,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): int
    {
        $datapackFiles = glob(Yii::getAlias(self::DATAPACK_DIR) . '/*.json');

        if (empty($datapackFiles)) {
            $this->stdout("No datapacks found in " . self::DATAPACK_DIR . "\n");
            return ExitCode::OK;
        }

        foreach ($datapackFiles as $file) {
            $this->stdout("Processing: {$file}\n");
            $this->processDatapack($file);
        }

        return ExitCode::OK;
    }

    private function processDatapack(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->stderr("Failed to read file: $filePath\n");
            return;
        }

        $json = json_decode($content, true);
        if ($json === null) {
            $this->stderr("Invalid JSON in file: $filePath\n");
            return;
        }

        $collectedAtStr = $json['collected_at'] ?? 'now';
        try {
            $collectedAt = new DateTimeImmutable($collectedAtStr);
        } catch (\Exception $e) {
            $collectedAt = new DateTimeImmutable();
        }

        foreach ($json['companies'] ?? [] as $companyData) {
            $ticker = $companyData['ticker'] ?? null;
            if (!$ticker) {
                continue;
            }

            // Ensure company exists
            $companyId = $this->companyQuery->findOrCreate($ticker);

            $data = $companyData['company_data'] ?? [];

            // Import financials
            if (isset($data['financials'])) {
                $this->importFinancials($companyId, $data['financials'], $collectedAt);
            }

            // Import quarters
            if (isset($data['quarters'])) {
                $this->importQuarters($companyId, $data['quarters'], $collectedAt);
            }

            // Import valuation snapshot
            if (isset($data['valuation'])) {
                $this->importValuation($companyId, $data['valuation'], $collectedAt);
            }
        }
    }

    private function importFinancials(int $companyId, array $financials, DateTimeImmutable $collectedAt): void
    {
        foreach ($financials as $periodKey => $data) {
            // Parse "FY2023" -> fiscal_year = 2023
            if (!preg_match('/^FY(\d{4})$/', $periodKey, $matches)) {
                continue;
            }

            $fiscalYear = (int) $matches[1];

            // Skip if already exists
            if ($this->annualQuery->exists($companyId, $fiscalYear)) {
                continue;
            }

            // Map keys carefully. Source JSON keys might vary, assuming standard map here.
            $this->annualQuery->insert([
                'company_id' => $companyId,
                'fiscal_year' => $fiscalYear,
                'period_end_date' => "{$fiscalYear}-12-31", // Default, ideally from data
                'revenue' => $data['revenue'] ?? null,
                'ebitda' => $data['ebitda'] ?? null,
                'net_income' => $data['net_income'] ?? null,
                'free_cash_flow' => $data['free_cash_flow'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'source_adapter' => $data['source'] ?? 'datapack_import',
                'collected_at' => $collectedAt->format('Y-m-d H:i:s'),
                'is_current' => 1,
                'version' => 1,
            ]);
        }
    }

    private function importQuarters(int $companyId, array $quarters, DateTimeImmutable $collectedAt): void
    {
        foreach ($quarters as $periodKey => $data) {
            // Parse "Q3_2023" -> quarter=3, year=2023
            if (!preg_match('/^Q(\d)_(\d{4})$/', $periodKey, $matches)) {
                continue;
            }

            $quarter = (int) $matches[1];
            $year = (int) $matches[2];

            // Check if exists
            if ($this->quarterlyQuery->findCurrentByCompanyAndQuarter($companyId, $year, $quarter)) {
                continue;
            }

            // Period end date approximation if not in data
            // Q1: Mar 31, Q2: Jun 30, Q3: Sep 30, Q4: Dec 31
            $month = $quarter * 3;
            $day = ($month == 3 || $month == 12) ? 31 : 30;
            $periodEnd = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $this->quarterlyQuery->insert([
                'company_id' => $companyId,
                'fiscal_year' => $year,
                'fiscal_quarter' => $quarter,
                'period_end_date' => $data['date'] ?? $periodEnd,
                'revenue' => $data['revenue'] ?? null,
                'net_income' => $data['net_income'] ?? null,
                'ebitda' => $data['ebitda'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'source_adapter' => $data['source'] ?? 'datapack_import',
                'collected_at' => $collectedAt->format('Y-m-d H:i:s'),
                'is_current' => 1,
                'version' => 1,
            ]);
        }
    }

    private function importValuation(int $companyId, array $valuation, DateTimeImmutable $collectedAt): void
    {
        $snapshotDate = $collectedAt->format('Y-m-d');

        if ($this->valuationQuery->findByCompanyAndDate($companyId, new DateTimeImmutable($snapshotDate))) {
            return;
        }

        // Map valuation metrics
        // Structure in design doc example: "market_cap": {"value": 420..., "currency": "USD", ...}
        // Need to handle nested value/source structure if that's how it is.
        // Design doc example: "market_cap": {"value": ..., "source": ...}

        $flattened = [];
        $source = 'datapack_import';
        $currency = 'USD';

        foreach ($valuation as $key => $item) {
            if (is_array($item) && isset($item['value'])) {
                $flattened[$key] = $item['value'];
                if (isset($item['source'])) {
                    $source = $item['source'];
                }
                if (isset($item['currency'])) {
                    $currency = $item['currency'];
                }
            } else {
                $flattened[$key] = $item;
            }
        }

        $this->valuationQuery->insert([
            'company_id' => $companyId,
            'snapshot_date' => $snapshotDate,
            'market_cap' => $flattened['market_cap'] ?? null,
            'enterprise_value' => $flattened['enterprise_value'] ?? null,
            'trailing_pe' => $flattened['trailing_pe'] ?? null,
            'forward_pe' => $flattened['forward_pe'] ?? null,
            'price_to_book' => $flattened['price_to_book'] ?? null,
            'price_to_sales' => $flattened['price_to_sales'] ?? null,
            'currency' => $currency,
            'source_adapter' => $source,
            'collected_at' => $collectedAt->format('Y-m-d H:i:s'),
            'retention_tier' => 'daily',
        ]);
    }
}
