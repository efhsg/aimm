<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\analysis\IndustryAnalysisContext;
use app\dto\GateError;
use app\dto\GateResult;
use app\dto\GateWarning;
use DateTimeImmutable;

/**
 * Validates analysis context completeness before analysis.
 *
 * Errors (blocking):
 * - Insufficient companies for comparison
 * - No companies with sufficient annual data
 * - No companies with market cap
 *
 * Warnings (non-blocking):
 * - Low company count
 * - Stale data
 */
final class AnalysisGateValidator implements AnalysisGateValidatorInterface
{
    private const ERROR_INSUFFICIENT_COMPANIES = 'INSUFFICIENT_COMPANIES';
    private const ERROR_NO_ANALYZABLE_COMPANIES = 'NO_ANALYZABLE_COMPANIES';

    private const WARNING_LOW_COMPANY_COUNT = 'LOW_COMPANY_COUNT';
    private const WARNING_STALE_DATA = 'STALE_DATA';
    private const WARNING_COMPANY_INSUFFICIENT_DATA = 'COMPANY_INSUFFICIENT_DATA';

    private const MIN_COMPANIES = 2;
    private const MIN_ANNUAL_YEARS = 2;
    private const RECOMMENDED_COMPANIES = 5;
    private const STALE_DAYS = 30;

    public function validate(IndustryAnalysisContext $context): GateResult
    {
        $errors = [];
        $warnings = [];

        $companyCount = count($context->companies);

        // 1. Minimum companies for comparison
        if ($companyCount < self::MIN_COMPANIES) {
            $errors[] = new GateError(
                code: self::ERROR_INSUFFICIENT_COMPANIES,
                message: "At least " . self::MIN_COMPANIES . " companies required for comparison, found {$companyCount}",
                path: 'companies',
            );
            return new GateResult(
                passed: false,
                errors: $errors,
                warnings: $warnings,
            );
        }

        // 2. Check each company for analyzability
        $analyzableCount = 0;
        foreach ($context->companies as $ticker => $company) {
            $annualCount = count($company->financials->annualData);
            $hasMarketCap = $company->valuation->marketCap->getBaseValue() !== null;

            if ($annualCount >= self::MIN_ANNUAL_YEARS && $hasMarketCap) {
                $analyzableCount++;
            } else {
                $reasons = [];
                if ($annualCount < self::MIN_ANNUAL_YEARS) {
                    $reasons[] = "{$annualCount} year(s) annual data (need " . self::MIN_ANNUAL_YEARS . ")";
                }
                if (!$hasMarketCap) {
                    $reasons[] = "missing market cap";
                }
                $warnings[] = new GateWarning(
                    code: self::WARNING_COMPANY_INSUFFICIENT_DATA,
                    message: "{$ticker}: " . implode(', ', $reasons),
                );
            }
        }

        if ($analyzableCount < self::MIN_COMPANIES) {
            $errors[] = new GateError(
                code: self::ERROR_NO_ANALYZABLE_COMPANIES,
                message: "Only {$analyzableCount} companies have sufficient data for analysis, need at least " . self::MIN_COMPANIES,
                path: 'companies',
            );
        }

        // 3. Recommend more companies (warning)
        if ($companyCount < self::RECOMMENDED_COMPANIES && $companyCount >= self::MIN_COMPANIES) {
            $warnings[] = new GateWarning(
                code: self::WARNING_LOW_COMPANY_COUNT,
                message: "Only {$companyCount} companies in group, recommend at least " . self::RECOMMENDED_COMPANIES,
            );
        }

        // 4. Data freshness (warning only)
        $daysSinceCollection = (new DateTimeImmutable())->diff($context->collectedAt)->days;
        if ($daysSinceCollection > self::STALE_DAYS) {
            $warnings[] = new GateWarning(
                code: self::WARNING_STALE_DATA,
                message: "Data is {$daysSinceCollection} days old (collected {$context->collectedAt->format('Y-m-d')})",
            );
        }

        return new GateResult(
            passed: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }
}
