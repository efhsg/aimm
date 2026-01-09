<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\IndustryResponse;
use app\dto\industry\SaveIndustryResult;
use app\dto\industry\UpdateIndustryRequest;
use app\queries\CompanyQuery;
use app\queries\IndustryQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for updating existing industries.
 */
final class UpdateIndustryHandler implements UpdateIndustryInterface
{
    public function __construct(
        private readonly IndustryQuery $industryQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function update(UpdateIndustryRequest $request): SaveIndustryResult
    {
        $this->logger->log(
            [
                'message' => 'Updating industry',
                'id' => $request->id,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'industry'
        );

        $existing = $this->industryQuery->findById($request->id);
        if ($existing === null) {
            return SaveIndustryResult::failure(['Industry not found.']);
        }

        $errors = $this->validate($request);
        if (!empty($errors)) {
            $this->logger->log(
                [
                    'message' => 'Validation failed for industry update',
                    'id' => $request->id,
                    'error_count' => count($errors),
                ],
                Logger::LEVEL_WARNING,
                'industry'
            );
            return SaveIndustryResult::failure($errors);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->industryQuery->update($request->id, [
                'name' => $request->name,
                'description' => $request->description,
                'policy_id' => $request->policyId,
                'updated_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Industry updated successfully',
                    'id' => $request->id,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'industry'
            );

            $industry = $this->industryQuery->findById($request->id);

            return SaveIndustryResult::success($this->toResponse($industry));
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to update industry',
                    'id' => $request->id,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'industry'
            );

            return SaveIndustryResult::failure(['Failed to save: ' . $e->getMessage()]);
        }
    }

    /**
     * @return string[]
     */
    private function validate(UpdateIndustryRequest $request): array
    {
        $errors = [];

        if (empty($request->name)) {
            $errors[] = 'Name is required.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toResponse(array $row): IndustryResponse
    {
        $industryId = (int) $row['id'];
        $companyCount = $this->companyQuery->countByIndustry($industryId);

        return new IndustryResponse(
            id: $industryId,
            slug: $row['slug'],
            name: $row['name'],
            sectorId: (int) $row['sector_id'],
            sectorSlug: $row['sector_slug'],
            sectorName: $row['sector_name'],
            description: $row['description'] ?? null,
            policyId: $row['policy_id'] !== null ? (int) $row['policy_id'] : null,
            policyName: null,
            isActive: (bool) $row['is_active'],
            companyCount: $companyCount,
            lastRunStatus: null,
            lastRunAt: null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] ?? null,
            updatedBy: $row['updated_by'] ?? null,
        );
    }
}
