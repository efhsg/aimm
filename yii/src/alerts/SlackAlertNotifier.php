<?php

declare(strict_types=1);

namespace app\alerts;

use GuzzleHttp\ClientInterface;
use Throwable;
use Yii;

/**
 * Sends alerts to a Slack webhook.
 */
final class SlackAlertNotifier implements AlertNotifierInterface
{
    private const SEVERITY_COLORS = [
        CollectionAlertEvent::SEVERITY_INFO => '#2196F3',
        CollectionAlertEvent::SEVERITY_WARNING => '#FF9800',
        CollectionAlertEvent::SEVERITY_CRITICAL => '#F44336',
    ];

    /**
     * @param string[] $supportedSeverities
     */
    public function __construct(
        private readonly string $webhookUrl,
        private readonly ClientInterface $httpClient,
        private readonly array $supportedSeverities = [
            CollectionAlertEvent::SEVERITY_WARNING,
            CollectionAlertEvent::SEVERITY_CRITICAL,
        ],
    ) {
    }

    public function notify(CollectionAlertEvent $event): void
    {
        $payload = [
            'attachments' => [
                [
                    'color' => self::SEVERITY_COLORS[$event->severity] ?? '#9E9E9E',
                    'title' => "AIMM Collection Alert: {$event->type}",
                    'text' => $event->message,
                    'fields' => $this->formatContextFields($event->context),
                    'ts' => $event->occurredAt->getTimestamp(),
                ],
            ],
        ];

        try {
            $this->httpClient->request('POST', $this->webhookUrl, [
                'json' => $payload,
                'timeout' => 5,
            ]);
        } catch (Throwable $e) {
            Yii::warning("Failed to send Slack alert: {$e->getMessage()}", 'alerts');
        }
    }

    public function supports(string $severity): bool
    {
        return in_array($severity, $this->supportedSeverities, true);
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array{title: string, value: string, short: bool}>
     */
    private function formatContextFields(array $context): array
    {
        $fields = [];
        foreach ($context as $key => $value) {
            $stringValue = is_scalar($value) ? (string) $value : json_encode($value);
            $fields[] = [
                'title' => ucfirst(str_replace('_', ' ', $key)),
                'value' => $stringValue,
                'short' => strlen($stringValue) < 40,
            ];
        }
        return $fields;
    }
}
