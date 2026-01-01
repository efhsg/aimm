<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\UpdateCollectionPolicyRequest;
use app\queries\CollectionPolicyQuery;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for updating collection policies.
 */
final class UpdateCollectionPolicyHandler implements UpdateCollectionPolicyInterface
{
    public function __construct(
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function update(UpdateCollectionPolicyRequest $request): CollectionPolicyResult
    {
        $this->logger->log(
            [
                'message' => 'Updating collection policy',
                'id' => $request->id,
                'name' => $request->name,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collectionpolicy'
        );

        $existing = $this->policyQuery->findById($request->id);
        if ($existing === null) {
            return CollectionPolicyResult::failure(['Policy not found.']);
        }

        // Validate name
        if (trim($request->name) === '') {
            return CollectionPolicyResult::failure(['Name is required.']);
        }

        // Validate JSON fields
        $jsonErrors = $this->validateJsonFields($request);
        if (!empty($jsonErrors)) {
            return CollectionPolicyResult::failure($jsonErrors);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->policyQuery->update($request->id, [
                'name' => $request->name,
                'description' => $request->description,
                'history_years' => $request->historyYears,
                'quarters_to_fetch' => $request->quartersToFetch,
                'valuation_metrics' => $request->valuationMetricsJson ?: '[]',
                'annual_financial_metrics' => $request->annualFinancialMetricsJson,
                'quarterly_financial_metrics' => $request->quarterlyFinancialMetricsJson,
                'operational_metrics' => $request->operationalMetricsJson,
                'commodity_benchmark' => $request->commodityBenchmark,
                'margin_proxy' => $request->marginProxy,
                'sector_index' => $request->sectorIndex,
                'required_indicators' => $request->requiredIndicatorsJson,
                'optional_indicators' => $request->optionalIndicatorsJson,
                'updated_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $policy = $this->policyQuery->findById($request->id);

            $this->logger->log(
                [
                    'message' => 'Collection policy updated',
                    'id' => $request->id,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'collectionpolicy'
            );

            return CollectionPolicyResult::success($policy);
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to update collection policy',
                    'id' => $request->id,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collectionpolicy'
            );

            return CollectionPolicyResult::failure(['Failed to update policy: ' . $e->getMessage()]);
        }
    }

    /**
     * @return string[]
     */
    private function validateJsonFields(UpdateCollectionPolicyRequest $request): array
    {
        $errors = [];

        $jsonFields = [
            'valuationMetricsJson' => 'Valuation metrics',
            'annualFinancialMetricsJson' => 'Annual financial metrics',
            'quarterlyFinancialMetricsJson' => 'Quarterly financial metrics',
            'operationalMetricsJson' => 'Operational metrics',
            'requiredIndicatorsJson' => 'Required indicators',
            'optionalIndicatorsJson' => 'Optional indicators',
        ];

        foreach ($jsonFields as $field => $label) {
            $value = $request->$field;
            if ($value !== null && $value !== '') {
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "{$label} contains invalid JSON: " . json_last_error_msg();
                }
            }
        }

        return $errors;
    }
}
