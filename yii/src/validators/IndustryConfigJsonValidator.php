<?php

declare(strict_types=1);

namespace app\validators;

use yii\validators\Validator;

/**
 * Validates config_json for industry_config records.
 *
 * Performs three validation checks:
 * 1. JSON parse validation (valid JSON string)
 * 2. JSON schema validation against industry-config.schema.json
 * 3. Semantic validation (config_json.id must match industry_id)
 */
final class IndustryConfigJsonValidator extends Validator
{
    private const SCHEMA_FILE = 'industry-config.schema.json';

    public SchemaValidatorInterface $schemaValidator;
    public string $industryIdAttribute = 'industry_id';

    public function init(): void
    {
        parent::init();

        if ($this->message === null) {
            $this->message = 'Invalid configuration JSON.';
        }
    }

    /**
     * @param \yii\base\Model $model
     */
    public function validateAttribute($model, $attribute): void
    {
        $json = $model->$attribute;

        if (!is_string($json) || $json === '') {
            $this->addError($model, $attribute, 'Configuration JSON is required.');
            return;
        }

        $parseResult = $this->validateJsonSyntax($json);
        if ($parseResult !== null) {
            $this->addError($model, $attribute, $parseResult);
            return;
        }

        $schemaResult = $this->schemaValidator->validate($json, self::SCHEMA_FILE);
        if (!$schemaResult->isValid()) {
            foreach ($schemaResult->getErrors() as $error) {
                $this->addError($model, $attribute, $error);
            }
            return;
        }

        $semanticResult = $this->validateSemantics($json, $model->{$this->industryIdAttribute});
        if ($semanticResult !== null) {
            $this->addError($model, $attribute, $semanticResult);
        }
    }

    protected function validateValue($value): ?array
    {
        if (!is_string($value) || $value === '') {
            return ['Configuration JSON is required.', []];
        }

        $parseResult = $this->validateJsonSyntax($value);
        if ($parseResult !== null) {
            return [$parseResult, []];
        }

        $schemaResult = $this->schemaValidator->validate($value, self::SCHEMA_FILE);
        if (!$schemaResult->isValid()) {
            $errors = $schemaResult->getErrors();
            return [$errors[0] ?? 'Schema validation failed.', []];
        }

        return null;
    }

    private function validateJsonSyntax(string $json): ?string
    {
        json_decode($json);
        $error = json_last_error();

        if ($error === JSON_ERROR_NONE) {
            return null;
        }

        return match ($error) {
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded.',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON.',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded.',
            JSON_ERROR_SYNTAX => 'JSON syntax error.',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters.',
            default => 'JSON parse error: ' . json_last_error_msg(),
        };
    }

    private function validateSemantics(string $json, ?string $industryId): ?string
    {
        $data = json_decode($json);

        if (!is_object($data)) {
            return 'Configuration must be a JSON object.';
        }

        if (!isset($data->id)) {
            return 'Configuration must contain an "id" field.';
        }

        if ($industryId !== null && $data->id !== $industryId) {
            return sprintf(
                'Configuration "id" (%s) must match industry_id (%s).',
                $data->id,
                $industryId
            );
        }

        return null;
    }
}
