<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Result of collection gate validation.
 */
final readonly class GateResult
{
    /**
     * @param list<GateError> $errors
     * @param list<GateWarning> $warnings
     */
    public function __construct(
        public bool $passed,
        public array $errors,
        public array $warnings,
    ) {
    }

    /**
     * Get all error codes.
     *
     * @return list<string>
     */
    public function getErrorCodes(): array
    {
        return array_map(static fn (GateError $e) => $e->code, $this->errors);
    }

    /**
     * Check if a specific error code is present.
     */
    public function hasErrorCode(string $code): bool
    {
        return in_array($code, $this->getErrorCodes(), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'errors' => array_map(static fn (GateError $e) => $e->toArray(), $this->errors),
            'warnings' => array_map(static fn (GateWarning $w) => $w->toArray(), $this->warnings),
        ];
    }
}
