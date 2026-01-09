<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\IndustryResponse;
use app\dto\industry\SaveIndustryResult;
use app\dto\industry\ToggleIndustryRequest;
use app\queries\CompanyQuery;
use app\queries\IndustryQuery;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for toggling industry active status.
 */
final class ToggleIndustryHandler implements ToggleIndustryInterface
{
    public function __construct(
        private readonly IndustryQuery $industryQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function toggle(ToggleIndustryRequest $request): SaveIndustryResult
    {
        $this->logger->log(
            [
                'message' => 'Toggling industry status',
                'id' => $request->id,
                'is_active' => $request->isActive,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'industry'
        );

        $existing = $this->industryQuery->findById($request->id);
        if ($existing === null) {
            return SaveIndustryResult::failure(['Industry not found.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if ($request->isActive) {
                $this->industryQuery->activate($request->id);
            } else {
                $this->industryQuery->deactivate($request->id);
            }

            $this->industryQuery->update($request->id, [
                'updated_by' => $request->actorUsername,
            ]);

            $transaction->commit();

            $action = $request->isActive ? 'activated' : 'deactivated';
            $this->logger->log(
                [
                    'message' => "Industry {$action} successfully",
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
                    'message' => 'Failed to toggle industry status',
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
