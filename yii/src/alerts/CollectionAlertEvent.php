<?php

declare(strict_types=1);

namespace app\alerts;

use DateTimeImmutable;

/**
 * Event representing a collection alert.
 */
final readonly class CollectionAlertEvent
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $severity,
        public string $type,
        public string $message,
        public array $context = [],
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }
}
