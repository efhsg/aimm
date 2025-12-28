<?php

declare(strict_types=1);

namespace app\alerts;

use DateTimeImmutable;

/**
 * Dispatches collection alerts to configured notifiers.
 */
final class AlertDispatcher
{
    /**
     * @param AlertNotifierInterface[] $notifiers
     */
    public function __construct(
        private readonly array $notifiers = [],
    ) {
    }

    public function dispatch(CollectionAlertEvent $event): void
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier->supports($event->severity)) {
                $notifier->notify($event);
            }
        }
    }

    public function alertBlocked(
        string $domain,
        string $url,
        ?DateTimeImmutable $retryAfter = null,
    ): void {
        $this->dispatch(new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: "Data source {$domain} has blocked collection requests",
            context: [
                'domain' => $domain,
                'blocked_url' => $url,
                'retry_after' => $retryAfter?->format('Y-m-d H:i:s T') ?? 'unknown',
                'action_required' => 'Review rate limiting settings or enable proxy rotation',
            ],
        ));
    }

    /**
     * @param list<object> $errors
     */
    public function alertGateFailed(
        string $industryId,
        string $datapackId,
        array $errors,
    ): void {
        $this->dispatch(new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_WARNING,
            type: 'GATE_FAILED',
            message: "Collection gate failed for {$industryId}",
            context: [
                'industry_id' => $industryId,
                'datapack_id' => $datapackId,
                'error_count' => count($errors),
                'first_error' => $errors[0]->message ?? 'Unknown',
            ],
        ));
    }
}
