<?php

declare(strict_types=1);

namespace tests\unit\alerts;

use app\alerts\CollectionAlertEvent;
use app\alerts\EmailAlertNotifier;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Exception;
use yii\mail\MailerInterface;
use yii\mail\MessageInterface;

/**
 * @covers \app\alerts\EmailAlertNotifier
 */
final class EmailAlertNotifierTest extends Unit
{
    public function testNotifySendsEmailWithCorrectContent(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Data source blocked',
            context: ['domain' => 'yahoo.com', 'blocked_url' => 'https://yahoo.com/quote/AAPL'],
            occurredAt: new DateTimeImmutable('2025-01-15 10:30:00 UTC'),
        );

        $message = $this->createMock(MessageInterface::class);
        $message->expects($this->once())
            ->method('setFrom')
            ->with('alerts@aimm.dev')
            ->willReturnSelf();
        $message->expects($this->once())
            ->method('setTo')
            ->with('admin@example.com')
            ->willReturnSelf();
        $message->expects($this->once())
            ->method('setSubject')
            ->with('[critical] AIMM: SOURCE_BLOCKED')
            ->willReturnSelf();
        $message->expects($this->once())
            ->method('setTextBody')
            ->with($this->callback(function (string $body): bool {
                return str_contains($body, 'Severity: critical')
                    && str_contains($body, 'Type: SOURCE_BLOCKED')
                    && str_contains($body, '2025-01-15 10:30:00')
                    && str_contains($body, 'Data source blocked')
                    && str_contains($body, 'domain: yahoo.com')
                    && str_contains($body, 'blocked_url: https://yahoo.com/quote/AAPL');
            }))
            ->willReturnSelf();
        $message->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('compose')
            ->willReturn($message);

        $notifier = new EmailAlertNotifier(
            mailer: $mailer,
            recipientEmail: 'admin@example.com',
            fromEmail: 'alerts@aimm.dev',
        );

        $notifier->notify($event);
    }

    public function testNotifyFormatsNestedContextAsJson(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'GATE_FAILED',
            message: 'Gate check failed',
            context: ['nested_data' => ['key' => 'value', 'count' => 5]],
        );

        $message = $this->createMock(MessageInterface::class);
        $message->method('setFrom')->willReturnSelf();
        $message->method('setTo')->willReturnSelf();
        $message->method('setSubject')->willReturnSelf();
        $message->expects($this->once())
            ->method('setTextBody')
            ->with($this->callback(function (string $body): bool {
                return str_contains($body, 'nested_data: {"key":"value","count":5}');
            }))
            ->willReturnSelf();
        $message->method('send')->willReturn(true);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('compose')->willReturn($message);

        $notifier = new EmailAlertNotifier(
            mailer: $mailer,
            recipientEmail: 'admin@example.com',
            fromEmail: 'alerts@aimm.dev',
        );

        $notifier->notify($event);
    }

    public function testNotifyHandlesEmptyContext(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Test alert',
            context: [],
        );

        $message = $this->createMock(MessageInterface::class);
        $message->method('setFrom')->willReturnSelf();
        $message->method('setTo')->willReturnSelf();
        $message->method('setSubject')->willReturnSelf();
        $message->expects($this->once())
            ->method('setTextBody')
            ->with($this->callback(function (string $body): bool {
                return !str_contains($body, 'Context:');
            }))
            ->willReturnSelf();
        $message->method('send')->willReturn(true);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('compose')->willReturn($message);

        $notifier = new EmailAlertNotifier(
            mailer: $mailer,
            recipientEmail: 'admin@example.com',
            fromEmail: 'alerts@aimm.dev',
        );

        $notifier->notify($event);
    }

    public function testSupportsByDefaultIncludesOnlyCritical(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $notifier = new EmailAlertNotifier(
            mailer: $mailer,
            recipientEmail: 'admin@example.com',
            fromEmail: 'alerts@aimm.dev',
        );

        $this->assertFalse($notifier->supports(CollectionAlertEvent::SEVERITY_INFO));
        $this->assertFalse($notifier->supports(CollectionAlertEvent::SEVERITY_WARNING));
        $this->assertTrue($notifier->supports(CollectionAlertEvent::SEVERITY_CRITICAL));
    }

    public function testSupportsRespectsCustomSeverities(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $notifier = new EmailAlertNotifier(
            mailer: $mailer,
            recipientEmail: 'admin@example.com',
            fromEmail: 'alerts@aimm.dev',
            supportedSeverities: [
                CollectionAlertEvent::SEVERITY_WARNING,
                CollectionAlertEvent::SEVERITY_CRITICAL,
            ],
        );

        $this->assertFalse($notifier->supports(CollectionAlertEvent::SEVERITY_INFO));
        $this->assertTrue($notifier->supports(CollectionAlertEvent::SEVERITY_WARNING));
        $this->assertTrue($notifier->supports(CollectionAlertEvent::SEVERITY_CRITICAL));
    }

    public function testNotifyHandlesMailerExceptionGracefully(): void
    {
        $event = new CollectionAlertEvent(
            severity: CollectionAlertEvent::SEVERITY_CRITICAL,
            type: 'SOURCE_BLOCKED',
            message: 'Test alert',
        );

        $message = $this->createMock(MessageInterface::class);
        $message->method('setFrom')->willReturnSelf();
        $message->method('setTo')->willReturnSelf();
        $message->method('setSubject')->willReturnSelf();
        $message->method('setTextBody')->willReturnSelf();
        $message->expects($this->once())
            ->method('send')
            ->willThrowException(new Exception('SMTP connection failed'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('compose')->willReturn($message);

        $notifier = new EmailAlertNotifier(
            mailer: $mailer,
            recipientEmail: 'admin@example.com',
            fromEmail: 'alerts@aimm.dev',
        );

        $notifier->notify($event);

        $this->assertTrue(true);
    }
}
