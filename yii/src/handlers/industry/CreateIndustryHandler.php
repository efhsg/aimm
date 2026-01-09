<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\CreateIndustryRequest;
use app\dto\industry\IndustryResponse;
use app\dto\industry\SaveIndustryResult;
use app\queries\CompanyQuery;
use app\queries\IndustryQuery;
use app\queries\SectorQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for creating new industries.
 */
final class CreateIndustryHandler implements CreateIndustryInterface
{
    public function __construct(
        private readonly IndustryQuery $industryQuery,
        private readonly SectorQuery $sectorQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function create(CreateIndustryRequest $request): SaveIndustryResult
    {
        $this->logger->log(
            [
                'message' => 'Creating industry',
                'slug' => $request->slug,
                'sector_id' => $request->sectorId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'industry'
        );

        $errors = $this->validate($request);
        if (!empty($errors)) {
            $this->logger->log(
                [
                    'message' => 'Validation failed for industry',
                    'slug' => $request->slug,
                    'error_count' => count($errors),
                ],
                Logger::LEVEL_WARNING,
                'industry'
            );
            return SaveIndustryResult::failure($errors);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $industryId = $this->industryQuery->insert([
                'slug' => $request->slug,
                'name' => $request->name,
                'sector_id' => $request->sectorId,
                'description' => $request->description,
                'policy_id' => $request->policyId,
                'is_active' => $request->isActive ? 1 : 0,
                'created_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Industry created successfully',
                    'slug' => $request->slug,
                    'id' => $industryId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'industry'
            );

            $industry = $this->industryQuery->findById($industryId);

            return SaveIndustryResult::success($this->toResponse($industry));
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to create industry',
                    'slug' => $request->slug,
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
    private function validate(CreateIndustryRequest $request): array
    {
        $errors = [];

        if (empty($request->name)) {
            $errors[] = 'Name is required.';
        }

        if (empty($request->slug)) {
            $errors[] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $request->slug)) {
            $errors[] = 'Slug must contain only lowercase letters, numbers, and hyphens.';
        } elseif ($this->industryQuery->findBySlug($request->slug) !== null) {
            $errors[] = 'An industry with this slug already exists.';
        }

        if ($request->sectorId <= 0) {
            $errors[] = 'Sector is required.';
        } elseif ($this->sectorQuery->findById($request->sectorId) === null) {
            $errors[] = 'Invalid sector ID.';
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
