<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\industryconfig\IndustryConfigListResponse;
use app\dto\industryconfig\IndustryConfigResponse;
use app\models\IndustryConfig;
use app\validators\SchemaValidatorInterface;
use DateTimeImmutable;

/**
 * Query class for listing and retrieving industry configs in the admin UI.
 *
 * Uses ActiveRecord and model query scopes. Does NOT validate config_json,
 * allowing admins to view and manage configs with invalid JSON.
 */
final class IndustryConfigListQuery
{
    private const SCHEMA_FILE = 'industry-config.schema.json';

    public function __construct(
        private ?SchemaValidatorInterface $schemaValidator = null,
    ) {
    }
    /**
     * List all industry configs with optional filtering.
     *
     * @param bool|null $isActive Filter by active status (null = all)
     * @param string|null $search Search in industry_id and name
     * @param string $orderBy Column to order by
     * @param string $orderDirection ASC or DESC
     */
    public function list(
        ?bool $isActive = null,
        ?string $search = null,
        string $orderBy = 'name',
        string $orderDirection = 'ASC',
    ): IndustryConfigListResponse {
        $query = IndustryConfig::find();

        if ($isActive === true) {
            $query->active();
        } elseif ($isActive === false) {
            $query->inactive();
        }

        if ($search !== null && $search !== '') {
            $query->andWhere([
                'or',
                ['like', 'industry_id', $search],
                ['like', 'name', $search],
            ]);
        }

        $allowedOrderColumns = ['name', 'industry_id', 'is_active', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns, true)) {
            $orderBy = 'name';
        }

        $orderDirection = strtoupper($orderDirection) === 'DESC' ? SORT_DESC : SORT_ASC;
        $query->orderBy([$orderBy => $orderDirection]);

        $models = $query->all();
        $items = array_map(
            fn (IndustryConfig $model): IndustryConfigResponse => $this->toResponse($model),
            $models
        );

        return new IndustryConfigListResponse(
            items: $items,
            total: count($items),
        );
    }

    /**
     * Find a single industry config by industry_id.
     *
     * Returns configs regardless of active status.
     */
    public function findByIndustryId(string $industryId): ?IndustryConfigResponse
    {
        $model = IndustryConfig::find()
            ->byIndustryId($industryId)
            ->one();

        if ($model === null) {
            return null;
        }

        return $this->toResponse($model);
    }

    /**
     * Check if an industry_id already exists.
     */
    public function exists(string $industryId): bool
    {
        return IndustryConfig::find()
            ->byIndustryId($industryId)
            ->exists();
    }

    /**
     * Get counts by active status.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    public function getCounts(): array
    {
        $total = IndustryConfig::find()->count();
        $active = IndustryConfig::find()->active()->count();

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'inactive' => (int) $total - (int) $active,
        ];
    }

    private function toResponse(IndustryConfig $model): IndustryConfigResponse
    {
        return new IndustryConfigResponse(
            id: (int) $model->id,
            industryId: $model->industry_id,
            name: $model->name,
            configJson: $model->config_json,
            isActive: (bool) $model->is_active,
            createdAt: new DateTimeImmutable($model->created_at),
            updatedAt: new DateTimeImmutable($model->updated_at),
            createdBy: $model->created_by,
            updatedBy: $model->updated_by,
            isJsonValid: $this->validateConfigJson($model->config_json),
        );
    }

    private function validateConfigJson(string $configJson): bool
    {
        if ($this->schemaValidator === null) {
            return true;
        }

        $decoded = json_decode($configJson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $result = $this->schemaValidator->validate($configJson, self::SCHEMA_FILE);
        return $result->isValid();
    }
}
