<?php

declare(strict_types=1);

namespace app\handlers\industryconfig;

use app\dto\industryconfig\IndustryConfigResponse;
use app\dto\industryconfig\SaveIndustryConfigResult;
use app\dto\industryconfig\UpdateIndustryConfigRequest;
use app\models\IndustryConfig;
use DateTimeImmutable;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for updating existing industry config records.
 *
 * Validates config_json against schema, derives name from config_json,
 * and stamps updated_by. The industry_id is immutable.
 */
final class UpdateIndustryConfigHandler implements UpdateIndustryConfigInterface
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function update(UpdateIndustryConfigRequest $request): SaveIndustryConfigResult
    {
        $this->logger->log(
            [
                'message' => 'Updating industry config',
                'industry_id' => $request->industryId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'industryconfig'
        );

        $model = IndustryConfig::find()
            ->where(['industry_id' => $request->industryId])
            ->one();

        if ($model === null) {
            $this->logger->log(
                [
                    'message' => 'Industry config not found for update',
                    'industry_id' => $request->industryId,
                ],
                Logger::LEVEL_WARNING,
                'industryconfig'
            );
            return SaveIndustryConfigResult::failure(['Industry config not found.']);
        }

        $name = $this->extractNameFromJson($request->configJson);
        if ($name === null) {
            $this->logger->log(
                [
                    'message' => 'Failed to extract name from config_json',
                    'industry_id' => $request->industryId,
                ],
                Logger::LEVEL_WARNING,
                'industryconfig'
            );
            return SaveIndustryConfigResult::failure(['Could not extract name from configuration JSON.']);
        }

        $model->scenario = IndustryConfig::SCENARIO_UPDATE;
        $model->name = $name;
        $model->config_json = $request->configJson;
        $model->updated_by = $request->actorUsername;

        if (!$model->validate()) {
            $errors = $this->flattenErrors($model->getErrors());
            $this->logger->log(
                [
                    'message' => 'Validation failed for industry config update',
                    'industry_id' => $request->industryId,
                    'error_count' => count($errors),
                ],
                Logger::LEVEL_WARNING,
                'industryconfig'
            );
            return SaveIndustryConfigResult::failure($errors);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            if (!$model->save(false)) {
                throw new \RuntimeException('Failed to save industry config.');
            }

            // Refresh model to get actual values from database (timestamps are Expressions before save)
            $model->refresh();

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Industry config updated successfully',
                    'industry_id' => $request->industryId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'industryconfig'
            );

            return SaveIndustryConfigResult::success($this->toResponse($model));
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to update industry config',
                    'industry_id' => $request->industryId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'industryconfig'
            );

            return SaveIndustryConfigResult::failure(['Failed to save: ' . $e->getMessage()]);
        }
    }

    private function extractNameFromJson(string $json): ?string
    {
        $data = json_decode($json);

        if (!is_object($data) || !isset($data->name) || !is_string($data->name)) {
            return null;
        }

        return $data->name;
    }

    /**
     * @param array<string, string[]> $errors
     * @return string[]
     */
    private function flattenErrors(array $errors): array
    {
        $flat = [];
        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $flat[] = $message;
            }
        }
        return $flat;
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
        );
    }
}
