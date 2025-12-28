<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\ValidationResult;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Yii;

/**
 * JSON Schema validation using opis/json-schema.
 */
final class SchemaValidator implements SchemaValidatorInterface
{
    private Validator $validator;
    private string $schemaPath;

    public function __construct(?string $schemaPath = null)
    {
        $this->schemaPath = $schemaPath ?? Yii::getAlias('@app/config/schemas');
        $this->validator = new Validator();
        $this->validator->resolver()->registerPrefix(
            'https://aimm.dev/schemas/',
            $this->schemaPath
        );
    }

    public function validate(string $json, string $schemaFile): ValidationResult
    {
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ValidationResult(
                valid: false,
                errors: ['Invalid JSON: ' . json_last_error_msg()],
            );
        }

        $result = $this->validator->validate(
            $data,
            "https://aimm.dev/schemas/{$schemaFile}"
        );

        if ($result->isValid()) {
            return new ValidationResult(valid: true, errors: []);
        }

        $formatter = new ErrorFormatter();
        $formattedErrors = $formatter->format($result->error(), false);

        return new ValidationResult(
            valid: false,
            errors: $this->flattenErrors($formattedErrors),
        );
    }

    /**
     * @param array<string, list<string>> $errors
     * @return list<string>
     */
    private function flattenErrors(array $errors): array
    {
        $flat = [];
        foreach ($errors as $path => $messages) {
            foreach ($messages as $message) {
                $flat[] = "{$path}: {$message}";
            }
        }

        return $flat;
    }
}
