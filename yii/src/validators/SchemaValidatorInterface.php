<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\ValidationResult;

/**
 * Interface for JSON schema validation.
 */
interface SchemaValidatorInterface
{
    /**
     * Validate JSON string against a schema file.
     *
     * @param string $json The JSON string to validate
     * @param string $schemaFile The schema file name (e.g., 'industry-datapack.schema.json')
     */
    public function validate(string $json, string $schemaFile): ValidationResult;
}
