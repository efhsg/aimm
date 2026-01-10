<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\GateError;
use app\dto\GateResult;
use app\dto\GateWarning;
use app\dto\IndustryConfig;
use app\enums\CollectionStatus;

/**
 * Validates collection results before proceeding to analysis.
 */
final class CollectionGateValidator implements CollectionGateValidatorInterface
{
    public function createPassingResult(): GateResult
    {
        return new GateResult(
            passed: true,
            errors: [],
            warnings: [],
        );
    }

    /**
     * @param array<string, CollectionStatus> $companyStatuses
     */
    public function validateResults(
        array $companyStatuses,
        CollectionStatus $macroStatus,
        IndustryConfig $config
    ): GateResult {
        $errors = [];
        $warnings = [];

        // Validate all companies equally
        foreach ($companyStatuses as $ticker => $status) {
            if ($status === CollectionStatus::Failed) {
                $errors[] = new GateError(
                    code: 'COMPANY_FAILED',
                    message: "Company {$ticker} collection failed",
                    path: "companies.{$ticker}",
                );
            } elseif ($status === CollectionStatus::Partial) {
                $warnings[] = new GateWarning(
                    code: 'COMPANY_PARTIAL',
                    message: "Company {$ticker} has partial data",
                    path: "companies.{$ticker}",
                );
            }
        }

        // Validate macro status
        if ($macroStatus === CollectionStatus::Failed) {
            $errors[] = new GateError(
                code: 'MACRO_FAILED',
                message: 'Macro indicator collection failed',
                path: 'macro',
            );
        } elseif ($macroStatus === CollectionStatus::Partial) {
            $warnings[] = new GateWarning(
                code: 'MACRO_PARTIAL',
                message: 'Macro indicators partially collected',
                path: 'macro',
            );
        }

        // Check company coverage
        $configuredTickers = array_map(static fn ($c) => $c->ticker, $config->companies);
        $collectedTickers = array_keys($companyStatuses);
        $missingTickers = array_diff($configuredTickers, $collectedTickers);

        foreach ($missingTickers as $ticker) {
            $warnings[] = new GateWarning(
                code: 'MISSING_COMPANY',
                message: "Configured company {$ticker} was not collected",
                path: "companies.{$ticker}",
            );
        }

        return new GateResult(
            passed: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }
}
