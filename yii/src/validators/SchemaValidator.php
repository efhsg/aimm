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

        try {
            $formattedErrors = $formatter->format($result->error(), false);
            $errors = $this->flattenErrors($formattedErrors);
        } catch (\Throwable $e) {
            $errors = ['Schema validation failed: ' . $result->error()->keyword()];
        }

        return new ValidationResult(
            valid: false,
            errors: $errors,
        );
    }

    /**
     * @param array<string|int|null, mixed> $errors
     * @return list<string>
     */
    private function flattenErrors(array $errors): array
    {
        $flat = [];
        foreach ($errors as $path => $messages) {
            $pathStr = $path === null || $path === '' ? '/' : (string) $path;

            if (is_string($messages)) {
                $flat[] = "{$pathStr}: {$messages}";
                continue;
            }

            if (!is_array($messages)) {
                $flat[] = "{$pathStr}: " . (string) $messages;
                continue;
            }

            foreach ($messages as $message) {
                if (is_array($message)) {
                    foreach ($message as $nested) {
                        $flat[] = "{$pathStr}: {$nested}";
                    }
                    continue;
                }

                $flat[] = "{$pathStr}: {$message}";
            }
        }

        return $flat;
    }
}
