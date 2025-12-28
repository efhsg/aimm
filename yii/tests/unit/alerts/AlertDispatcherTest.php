<?php

declare(strict_types=1);

namespace tests\unit\alerts;

use app\alerts\AlertDispatcher;
use app\alerts\AlertNotifierInterface;
use app\alerts\CollectionAlertEvent;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\alerts\AlertDispatcher
 */
final class AlertDispatcherTest extends Unit
{
    public function testDispatchCallsNotifyOnSupportedNotifiers(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Test alert',
        );

        $supportedNotifier = $this->createMock(AlertNotifierInterface::class);
        $supportedNotifier->expects($this->once())
            ->method('supports')
            ->with(CollectionAlertEvent::SEVERITY_CRITICAL)
            ->willReturn(true);
        $supportedNotifier->expects($this->once())
            ->method('notify')
            ->with($event);

        $unsupportedNotifier = $this->createMock(AlertNotifierInterface::class);
        $unsupportedNotifier->expects($this->once())
            ->method('supports')
            ->with(CollectionAlertEvent::SEVERITY_CRITICAL)
            ->willReturn(false);
        $unsupportedNotifier->expects($this->never())
            ->method('notify');

        $dispatcher = new AlertDispatcher([$supportedNotifier, $unsupportedNotifier]);
        $dispatcher->dispatch($event);
    }

    public function testDispatchSkipsNotifiersWhenNoneSupport(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_INFO,
            type: 'COLLECTION_COMPLETE',
            message: 'Collection completed',
        );

        $notifier = $this->createMock(AlertNotifierInterface::class);
        $notifier->expects($this->once())
            ->method('supports')
            ->with(CollectionAlertEvent::SEVERITY_INFO)
            ->willReturn(false);
        $notifier->expects($this->never())
            ->method('notify');

        $dispatcher = new AlertDispatcher([$notifier]);
        $dispatcher->dispatch($event);
    }

    public function testDispatchCallsAllSupportedNotifiers(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_WARNING,
            type: 'GATE_FAILED',
            message: 'Gate check failed',
        );

        $notifier1 = $this->createMock(AlertNotifierInterface::class);
        $notifier1->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $notifier1->expects($this->once())
            ->method('notify')
            ->with($event);

        $notifier2 = $this->createMock(AlertNotifierInterface::class);
        $notifier2->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $notifier2->expects($this->once())
            ->method('notify')
            ->with($event);

        $dispatcher = new AlertDispatcher([$notifier1, $notifier2]);
        $dispatcher->dispatch($event);
    }

    public function testAlertBlockedDispatchesCriticalEvent(): void
    {
        $notifier = $this->createMock(AlertNotifierInterface::class);
        $notifier->expects($this->once())
            ->method('supports')
            ->with(CollectionAlertEvent::SEVERITY_CRITICAL)
            ->willReturn(true);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(function (CollectionAlertEvent $event): bool {
                return $event->severity === CollectionAlertEvent::SEVERITY_CRITICAL
                    && $event->type === 'SOURCE_BLOCKED'
                    && str_contains($event->message, 'yahoo.com')
                    && $event->context['domain'] === 'yahoo.com'
                    && $event->context['blocked_url'] === 'https://yahoo.com/quote/AAPL';
            }));

        $dispatcher = new AlertDispatcher([$notifier]);
        $dispatcher->alertBlocked(
            domain: 'yahoo.com',
            url: 'https://yahoo.com/quote/AAPL',
        );
    }

    public function testAlertBlockedIncludesRetryAfter(): void
    {
        $retryAfter = new DateTimeImmutable('2025-01-15 12:00:00');

        $notifier = $this->createMock(AlertNotifierInterface::class);
        $notifier->method('supports')->willReturn(true);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(function (CollectionAlertEvent $event) use ($retryAfter): bool {
                return str_contains($event->context['retry_after'], '2025-01-15 12:00:00');
            }));

        $dispatcher = new AlertDispatcher([$notifier]);
        $dispatcher->alertBlocked('test.com', 'https://test.com', $retryAfter);
    }

    public function testAlertGateFailedDispatchesWarningEvent(): void
    {
        $errors = [
            (object) ['message' => 'Missing required field: revenue'],
            (object) ['message' => 'Invalid value for margin'],
        ];

        $notifier = $this->createMock(AlertNotifierInterface::class);
        $notifier->expects($this->once())
            ->method('supports')
            ->with(CollectionAlertEvent::SEVERITY_WARNING)
            ->willReturn(true);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(function (CollectionAlertEvent $event) use ($errors): bool {
                return $event->severity === CollectionAlertEvent::SEVERITY_WARNING
                    && $event->type === 'GATE_FAILED'
                    && str_contains($event->message, 'oil_majors')
                    && $event->context['industry_id'] === 'oil_majors'
                    && $event->context['datapack_id'] === 'dp-123'
                    && $event->context['error_count'] === 2
                    && $event->context['first_error'] === 'Missing required field: revenue';
            }));

        $dispatcher = new AlertDispatcher([$notifier]);
        $dispatcher->alertGateFailed(
            industryId: 'oil_majors',
            datapackId: 'dp-123',
            errors: $errors,
        );
    }

    public function testDispatchWithNoNotifiers(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Test alert',
        );

        $dispatcher = new AlertDispatcher([]);
        $dispatcher->dispatch($event);

        $this->assertTrue(true);
    }

    public function testAlertGateFailedHandlesEmptyErrorsArray(): void
    {
        $notifier = $this->createMock(AlertNotifierInterface::class);
        $notifier->method('supports')->willReturn(true);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($this->callback(function (CollectionAlertEvent $event): bool {
                return $event->context['error_count'] === 0
                    && $event->context['first_error'] === 'Unknown';
            }));

        $dispatcher = new AlertDispatcher([$notifier]);
        $dispatcher->alertGateFailed(
            industryId: 'oil_majors',
            datapackId: 'dp-123',
            errors: [],
        );
    }
}
