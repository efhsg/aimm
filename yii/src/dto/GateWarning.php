<?php

declare(strict_types=1);

namespace app\dto;

/**
 * A gate validation warning (non-fatal issue).
 */
final readonly class GateWarning
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $path = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'path' => $this->path,
        ];
    }
}
