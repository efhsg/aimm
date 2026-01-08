<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\GateError;
use app\dto\GateResult;
use app\dto\GateWarning;
use app\dto\IndustryDataPack;
use DateTimeImmutable;

/**
 * Validates datapack completeness before analysis.
 *
 * Errors (blocking):
 * - Focal company not found
 * - Insufficient annual data for trend analysis
 * - Missing market cap
 * - No peers available
 *
 * Warnings (non-blocking):
 * - Low peer count
 * - Stale data
 */
final class AnalysisGateValidator implements AnalysisGateValidatorInterface
{
    private const ERROR_FOCAL_NOT_FOUND = 'FOCAL_NOT_FOUND';
    private const ERROR_INSUFFICIENT_ANNUAL_DATA = 'INSUFFICIENT_ANNUAL_DATA';
    private const ERROR_MISSING_MARKET_CAP = 'MISSING_MARKET_CAP';
    private const ERROR_NO_PEERS = 'NO_PEERS';

    private const WARNING_LOW_PEER_COUNT = 'LOW_PEER_COUNT';
    private const WARNING_STALE_DATA = 'STALE_DATA';

    private const MIN_ANNUAL_YEARS = 2;
    private const MIN_PEER_COUNT = 2;
    private const STALE_DAYS = 30;

    public function validate(IndustryDataPack $dataPack, string $focalTicker): GateResult
    {
        $errors = [];
        $warnings = [];

        // 1. Focal company exists
        if (!$dataPack->hasCompany($focalTicker)) {
            $errors[] = new GateError(
                code: self::ERROR_FOCAL_NOT_FOUND,
                message: "Focal company {$focalTicker} not found in datapack",
                path: "companies.{$focalTicker}",
            );
            // Early return - can't validate further without focal
            return new GateResult(
                passed: false,
                errors: $errors,
                warnings: $warnings,
            );
        }

        $focal = $dataPack->getCompany($focalTicker);

        // 2. Sufficient annual data for trend analysis
        $annualCount = count($focal->financials->annualData);
        if ($annualCount < self::MIN_ANNUAL_YEARS) {
            $errors[] = new GateError(
                code: self::ERROR_INSUFFICIENT_ANNUAL_DATA,
                message: "Focal company has {$annualCount} year(s) of annual data, minimum is " . self::MIN_ANNUAL_YEARS,
                path: "companies.{$focalTicker}.financials.annualData",
            );
        }

        // 3. Market cap present
        if ($focal->valuation->marketCap->getBaseValue() === null) {
            $errors[] = new GateError(
                code: self::ERROR_MISSING_MARKET_CAP,
                message: 'Focal company missing market cap',
                path: "companies.{$focalTicker}.valuation.marketCap",
            );
        }

        // 4. Peer count
        $peerCount = count($dataPack->companies) - 1;
        if ($peerCount === 0) {
            $errors[] = new GateError(
                code: self::ERROR_NO_PEERS,
                message: 'No peer companies found for comparison',
            );
        } elseif ($peerCount < self::MIN_PEER_COUNT) {
            $warnings[] = new GateWarning(
                code: self::WARNING_LOW_PEER_COUNT,
                message: "Only {$peerCount} peer(s) available, recommend minimum " . self::MIN_PEER_COUNT,
            );
        }

        // 5. Data freshness (warning only)
        $daysSinceCollection = (new DateTimeImmutable())->diff($dataPack->collectedAt)->days;
        if ($daysSinceCollection > self::STALE_DAYS) {
            $warnings[] = new GateWarning(
                code: self::WARNING_STALE_DATA,
                message: "Data is {$daysSinceCollection} days old (collected {$dataPack->collectedAt->format('Y-m-d')})",
            );
        }

        return new GateResult(
            passed: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }
}
