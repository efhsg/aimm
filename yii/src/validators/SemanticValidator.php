<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\CompanyData;
use app\dto\GateError;
use app\dto\GateResult;
use app\dto\GateWarning;
use app\dto\IndustryDataPack;
use DateTimeImmutable;

/**
 * Validates datapoint values against domain-specific rules.
 */
final class SemanticValidator implements SemanticValidatorInterface
{
    private const ERROR_RANGE_VIOLATION = 'SEMANTIC_RANGE_VIOLATION';
    private const ERROR_TEMPORAL_INVALID = 'SEMANTIC_TEMPORAL_INVALID';
    private const ERROR_INVALID_SOURCE_URL = 'INVALID_SOURCE_URL';
    private const ERROR_SOURCE_DOMAIN_NOT_ALLOWED = 'SOURCE_DOMAIN_NOT_ALLOWED';

    private const WARNING_RANGE_SUSPECT = 'SEMANTIC_RANGE_SUSPECT';
    private const WARNING_CROSS_FIELD = 'SEMANTIC_CROSS_FIELD';

    public function validate(IndustryDataPack $dataPack): GateResult
    {
        $errors = [];
        $warnings = [];
        $now = new DateTimeImmutable();

        foreach ($dataPack->companies as $ticker => $company) {
            $rangeResults = $this->validateRanges($ticker, $company);
            $errors = array_merge($errors, $rangeResults['errors']);
            $warnings = array_merge($warnings, $rangeResults['warnings']);

            $warnings = array_merge($warnings, $this->validateCrossField($ticker, $company));

            $temporalResults = $this->validateTemporal($ticker, $company, $now);
            $errors = array_merge($errors, $temporalResults['errors']);
            $warnings = array_merge($warnings, $temporalResults['warnings']);

            $errors = array_merge($errors, $this->validateSourceUrls($ticker, $company));
        }

        $macroResults = $this->validateMacroTemporalSanity($dataPack, $now);
        $errors = array_merge($errors, $macroResults['errors']);
        $warnings = array_merge($warnings, $macroResults['warnings']);

        return new GateResult(
            passed: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * @return array{errors: list<GateError>, warnings: list<GateWarning>}
     */
    private function validateRanges(string $ticker, CompanyData $company): array
    {
        $errors = [];
        $warnings = [];

        $valuation = $company->valuation;
        $metrics = [
            'market_cap' => $valuation->marketCap?->getBaseValue(),
            'fwd_pe' => $valuation->fwdPe?->value,
            'trailing_pe' => $valuation->trailingPe?->value,
            'ev_ebitda' => $valuation->evEbitda?->value,
            'div_yield' => $valuation->divYield?->value,
            'fcf_yield' => $valuation->fcfYield?->value,
            'net_debt_ebitda' => $valuation->netDebtEbitda?->value,
            'price_to_book' => $valuation->priceToBook?->value,
        ];

        foreach ($metrics as $name => $value) {
            if ($value === null) {
                continue;
            }

            $rules = SemanticValidationRules::RANGES[$name] ?? null;
            if ($rules === null) {
                continue;
            }

            if ($value < $rules['min'] || $value > $rules['max']) {
                $path = "companies.{$ticker}.valuation.{$name}.value";
                $message = "{$name} value {$value} is outside expected range [{$rules['min']}, {$rules['max']}] for {$ticker}";
                $severity = $rules['severity'] ?? 'warning';

                if ($severity === 'error') {
                    $errors[] = new GateError(
                        code: self::ERROR_RANGE_VIOLATION,
                        message: $message,
                        path: $path,
                    );
                } else {
                    $warnings[] = new GateWarning(
                        code: self::WARNING_RANGE_SUSPECT,
                        message: $message,
                        path: $path,
                    );
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return list<GateWarning>
     */
    private function validateCrossField(string $ticker, CompanyData $company): array
    {
        $warnings = [];
        $valuation = $company->valuation;

        // Check FCF yield consistency
        $fcfYield = $valuation->fcfYield?->value;
        $fcfTtm = $valuation->freeCashFlowTtm?->getBaseValue();
        $marketCap = $valuation->marketCap?->getBaseValue();

        if ($fcfYield !== null && $fcfTtm !== null && $marketCap !== null && $marketCap > 0) {
            $calculatedYield = ($fcfTtm / $marketCap) * 100;
            $tolerance = SemanticValidationRules::CROSS_FIELD_RULES['fcf_yield_consistency']['tolerance'];
            $diff = abs($fcfYield - $calculatedYield);
            $percentDiff = $diff / max(abs($fcfYield), abs($calculatedYield), 0.01);

            if ($percentDiff > $tolerance) {
                $warnings[] = new GateWarning(
                    code: self::WARNING_CROSS_FIELD,
                    message: sprintf(
                        'FCF yield (%.2f%%) inconsistent with calculated (%.2f%%) for %s',
                        $fcfYield,
                        $calculatedYield,
                        $ticker
                    ),
                    path: "companies.{$ticker}.valuation.fcf_yield.value",
                );
            }
        }

        // Check P/E ratio ordering
        $fwdPe = $valuation->fwdPe?->value;
        $trailingPe = $valuation->trailingPe?->value;

        if ($fwdPe !== null && $trailingPe !== null && $fwdPe > $trailingPe * 1.5) {
            $warnings[] = new GateWarning(
                code: self::WARNING_CROSS_FIELD,
                message: sprintf(
                    'Forward P/E (%.1f) significantly higher than trailing P/E (%.1f) for %s',
                    $fwdPe,
                    $trailingPe,
                    $ticker
                ),
                path: "companies.{$ticker}.valuation.fwd_pe.value",
            );
        }

        return $warnings;
    }

    /**
     * @return list<GateError>
     */
    private function validateSourceUrls(string $ticker, CompanyData $company): array
    {
        $errors = [];

        $datapoints = [
            'market_cap' => $company->valuation->marketCap,
            'fwd_pe' => $company->valuation->fwdPe,
            'trailing_pe' => $company->valuation->trailingPe,
            'ev_ebitda' => $company->valuation->evEbitda,
            'div_yield' => $company->valuation->divYield,
        ];

        foreach ($datapoints as $name => $datapoint) {
            if ($datapoint === null || $datapoint->sourceUrl === null) {
                continue;
            }

            // Non-http(s) schemes bypass allowlist (e.g., cache://)
            $scheme = parse_url($datapoint->sourceUrl, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
                continue;
            }

            $host = parse_url($datapoint->sourceUrl, PHP_URL_HOST);
            if ($host === false || $host === null) {
                $errors[] = new GateError(
                    code: self::ERROR_INVALID_SOURCE_URL,
                    message: "Invalid source_url format for {$name} in {$ticker}: {$datapoint->sourceUrl}",
                    path: "companies.{$ticker}.valuation.{$name}.source_url",
                );
                continue;
            }

            if (!in_array($host, SemanticValidationRules::ALLOWED_DOMAINS, true)) {
                $errors[] = new GateError(
                    code: self::ERROR_SOURCE_DOMAIN_NOT_ALLOWED,
                    message: "Source URL domain '{$host}' is not in allowlist for {$name} in {$ticker}",
                    path: "companies.{$ticker}.valuation.{$name}.source_url",
                );
            }
        }

        return $errors;
    }

    /**
     * @return array{errors: list<GateError>, warnings: list<GateWarning>}
     */
    private function validateTemporal(string $ticker, CompanyData $company, DateTimeImmutable $now): array
    {
        $errors = [];
        $warnings = [];
        $maxAgeDays = SemanticValidationRules::TEMPORAL_RULES['max_as_of_age_days'];
        $maxFutureDays = SemanticValidationRules::TEMPORAL_RULES['max_future_as_of_days'];

        $datapoints = [
            'market_cap' => $company->valuation->marketCap,
            'fwd_pe' => $company->valuation->fwdPe,
            'trailing_pe' => $company->valuation->trailingPe,
            'ev_ebitda' => $company->valuation->evEbitda,
            'div_yield' => $company->valuation->divYield,
            'fcf_yield' => $company->valuation->fcfYield,
            'net_debt_ebitda' => $company->valuation->netDebtEbitda,
            'price_to_book' => $company->valuation->priceToBook,
        ];

        foreach ($datapoints as $name => $datapoint) {
            if ($datapoint === null) {
                continue;
            }

            $asOf = $datapoint->asOf;

            // Check for future dates
            if ($asOf > $now) {
                $futureDays = $asOf->diff($now)->days;
                if ($futureDays > $maxFutureDays) {
                    $errors[] = new GateError(
                        code: self::ERROR_TEMPORAL_INVALID,
                        message: "{$name} for {$ticker} has future as_of date: {$asOf->format('Y-m-d')}",
                        path: "companies.{$ticker}.valuation.{$name}.as_of",
                    );
                }
                continue;
            }

            // Check for stale dates
            $daysSince = $now->diff($asOf)->days;
            if ($daysSince > $maxAgeDays) {
                $warnings[] = new GateWarning(
                    code: self::WARNING_RANGE_SUSPECT,
                    message: "{$name} for {$ticker} has stale as_of date: {$asOf->format('Y-m-d')} ({$daysSince} days old)",
                    path: "companies.{$ticker}.valuation.{$name}.as_of",
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @return array{errors: list<GateError>, warnings: list<GateWarning>}
     */
    private function validateMacroTemporalSanity(IndustryDataPack $dataPack, DateTimeImmutable $now): array
    {
        $errors = [];
        $warnings = [];
        $maxAgeDays = SemanticValidationRules::TEMPORAL_RULES['max_as_of_age_days'];
        $maxFutureDays = SemanticValidationRules::TEMPORAL_RULES['max_future_as_of_days'];

        $macroDatapoints = [
            'commodity_benchmark' => $dataPack->macro->commodityBenchmark,
            'margin_proxy' => $dataPack->macro->marginProxy,
        ];

        foreach ($macroDatapoints as $name => $datapoint) {
            if ($datapoint === null || $datapoint->value === null) {
                continue;
            }

            $asOf = $datapoint->asOf;

            // Check for future dates
            if ($asOf > $now) {
                $futureDays = $asOf->diff($now)->days;
                if ($futureDays > $maxFutureDays) {
                    $errors[] = new GateError(
                        code: self::ERROR_TEMPORAL_INVALID,
                        message: "Macro {$name} has future as_of date: {$asOf->format('Y-m-d')}",
                        path: "macro.{$name}.as_of",
                    );
                }
            } else {
                $daysSince = $now->diff($asOf)->days;
                if ($daysSince > $maxAgeDays) {
                    $warnings[] = new GateWarning(
                        code: self::WARNING_RANGE_SUSPECT,
                        message: "Macro {$name} has stale as_of date: {$asOf->format('Y-m-d')} ({$daysSince} days old)",
                        path: "macro.{$name}.as_of",
                    );
                }
            }

            // Macro prices should be positive
            if ($datapoint->value <= 0) {
                $errors[] = new GateError(
                    code: self::ERROR_RANGE_VIOLATION,
                    message: "Macro {$name} must be positive, got {$datapoint->value}",
                    path: "macro.{$name}.value",
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
