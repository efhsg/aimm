<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

/**
 * Value object for tracking fetch attempts.
 *
 * Records the outcome of each source fetch attempt for provenance and debugging.
 */
final readonly class SourceAttempt
{
    public function __construct(
        public string $url,
        public string $providerId,
        public DateTimeImmutable $attemptedAt,
        public string $outcome,
        public ?string $reason = null,
        public ?int $httpStatus = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'provider_id' => $this->providerId,
            'attempted_at' => $this->attemptedAt->format(DateTimeImmutable::ATOM),
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'http_status' => $this->httpStatus,
        ];
    }
}
