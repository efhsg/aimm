<?php

declare(strict_types=1);

namespace tests\unit\alerts;

use app\alerts\CollectionAlertEvent;
use app\alerts\SlackAlertNotifier;
use Codeception\Test\Unit;
use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

/**
 * @covers \app\alerts\SlackAlertNotifier
 */
final class SlackAlertNotifierTest extends Unit
{
    public function testNotifySendsPayloadToWebhook(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Data source blocked',
            context: ['domain' => 'yahoo.com', 'blocked_url' => 'https://yahoo.com/quote/AAPL'],
            occurredAt: new DateTimeImmutable('2025-01-15 10:30:00'),
        );

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://hooks.slack.com/services/xxx',
                $this->callback(function (array $options) use ($event): bool {
                    $payload = $options['json'] ?? [];
                    $attachment = $payload['attachments'][0] ?? [];

                    return $options['timeout'] === 5
                        && $attachment['color'] === '#F44336'
                        && $attachment['title'] === 'AIMM Collection Alert: SOURCE_BLOCKED'
                        && $attachment['text'] === 'Data source blocked'
                        && $attachment['ts'] === $event->occurredAt->getTimestamp()
                        && count($attachment['fields']) === 2;
                }),
            );

        $notifier = new SlackAlertNotifier(
            webhookUrl: 'https://hooks.slack.com/services/xxx',
            httpClient: $httpClient,
        );

        $notifier->notify($event);
    }

    public function testNotifyFormatsContextFieldsCorrectly(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_WARNING,
            type: 'GATE_FAILED',
            message: 'Gate check failed',
            context: [
                'industry_id' => 'oil_majors',
                'error_count' => 5,
                'nested_data' => ['key' => 'value'],
            ],
        );

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $fields = $options['json']['attachments'][0]['fields'] ?? [];

                    $industryField = $fields[0] ?? [];
                    $errorCountField = $fields[1] ?? [];
                    $nestedField = $fields[2] ?? [];

                    return $industryField['title'] === 'Industry id'
                        && $industryField['value'] === 'oil_majors'
                        && $industryField['short'] === true
                        && $errorCountField['title'] === 'Error count'
                        && $errorCountField['value'] === '5'
                        && $nestedField['title'] === 'Nested data'
                        && $nestedField['value'] === '{"key":"value"}';
                }),
            );

        $notifier = new SlackAlertNotifier(
            webhookUrl: 'https://hooks.slack.com/test',
            httpClient: $httpClient,
        );

        $notifier->notify($event);
    }

    public function testNotifyUsesCorrectColorsForSeverity(): void
    {
        $testCases = [
            CollectionAlertEvent::SEVERITY_INFO => '#2196F3',
            CollectionAlertEvent::SEVERITY_WARNING => '#FF9800',
            CollectionAlertEvent::SEVERITY_CRITICAL => '#F44336',
        ];

        foreach ($testCases as $severity => $expectedColor) {
            $event = new CollectionAlertEvent(
                severity: $severity,
                type: 'TEST',
                message: 'Test message',
            );

            $httpClient = $this->createMock(ClientInterface::class);
            $httpClient->expects($this->once())
                ->method('request')
                ->with(
                    $this->anything(),
                    $this->anything(),
                    $this->callback(function (array $options) use ($expectedColor): bool {
                        return ($options['json']['attachments'][0]['color'] ?? '') === $expectedColor;
                    }),
                );

            $notifier = new SlackAlertNotifier(
                webhookUrl: 'https://hooks.slack.com/test',
                httpClient: $httpClient,
                supportedSeverities: [$severity],
            );

            $notifier->notify($event);
        }
    }

    public function testSupportsByDefaultIncludesWarningAndCritical(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $notifier = new SlackAlertNotifier(
            webhookUrl: 'https://hooks.slack.com/test',
            httpClient: $httpClient,
        );

        $this->assertFalse($notifier->supports(CollectionAlertEvent::SEVERITY_INFO));
        $this->assertTrue($notifier->supports(CollectionAlertEvent::SEVERITY_WARNING));
        $this->assertTrue($notifier->supports(CollectionAlertEvent::SEVERITY_CRITICAL));
    }

    public function testSupportsRespectsCustomSeverities(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $notifier = new SlackAlertNotifier(
            webhookUrl: 'https://hooks.slack.com/test',
            httpClient: $httpClient,
            supportedSeverities: [CollectionAlertEvent::SEVERITY_INFO],
        );

        $this->assertTrue($notifier->supports(CollectionAlertEvent::SEVERITY_INFO));
        $this->assertFalse($notifier->supports(CollectionAlertEvent::SEVERITY_WARNING));
        $this->assertFalse($notifier->supports(CollectionAlertEvent::SEVERITY_CRITICAL));
    }

    public function testNotifyHandlesHttpClientExceptionGracefully(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Test alert',
        );

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new ConnectException(
                'Connection timeout',
                new Request('POST', 'https://hooks.slack.com/test'),
            ));

        $notifier = new SlackAlertNotifier(
            webhookUrl: 'https://hooks.slack.com/test',
            httpClient: $httpClient,
        );

        $notifier->notify($event);

        $this->assertTrue(true);
    }
}
