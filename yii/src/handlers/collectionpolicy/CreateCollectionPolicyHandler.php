<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\CreateCollectionPolicyRequest;
use app\queries\CollectionPolicyQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for creating collection policies.
 */
final class CreateCollectionPolicyHandler implements CreateCollectionPolicyInterface
{
    public function __construct(
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function create(CreateCollectionPolicyRequest $request): CollectionPolicyResult
    {
        $this->logger->log(
            [
                'message' => 'Creating collection policy',
                'slug' => $request->slug,
                'name' => $request->name,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collectionpolicy'
        );

        // Validate slug
        if (trim($request->slug) === '') {
            return CollectionPolicyResult::failure(['Slug is required.']);
        }

        if (!preg_match('/^[a-z0-9-]+$/', $request->slug)) {
            return CollectionPolicyResult::failure(['Slug must contain only lowercase letters, numbers, and hyphens.']);
        }

        // Check for duplicate slug
        $existing = $this->policyQuery->findBySlug($request->slug);
        if ($existing !== null) {
            return CollectionPolicyResult::failure(['A policy with this slug already exists.']);
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
            $now = new DateTimeImmutable();
            $id = $this->policyQuery->insert([
                'slug' => $request->slug,
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
                'created_by' => $request->actorUsername,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $transaction->commit();

            $policy = $this->policyQuery->findById($id);

            $this->logger->log(
                [
                    'message' => 'Collection policy created',
                    'id' => $id,
                    'slug' => $request->slug,
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
                    'message' => 'Failed to create collection policy',
                    'slug' => $request->slug,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collectionpolicy'
            );

            return CollectionPolicyResult::failure(['Failed to create policy: ' . $e->getMessage()]);
        }
    }

    /**
     * @return string[]
     */
    private function validateJsonFields(CreateCollectionPolicyRequest $request): array
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
