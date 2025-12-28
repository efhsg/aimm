<?php

declare(strict_types=1);

namespace app\alerts;

use Throwable;
use Yii;
use yii\mail\MailerInterface;

/**
 * Sends alerts via email using Yii mailer.
 */
final class EmailAlertNotifier implements AlertNotifierInterface
{
    /**
     * @param string[] $supportedSeverities
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $recipientEmail,
        private readonly string $fromEmail,
        private readonly array $supportedSeverities = [
            CollectionAlertEvent::SEVERITY_CRITICAL,
        ],
    ) {
    }

    public function notify(CollectionAlertEvent $event): void
    {
        $subject = "[{$event->severity}] AIMM: {$event->type}";

        $body = "Alert Details\n";
        $body .= "=============\n\n";
        $body .= "Severity: {$event->severity}\n";
        $body .= "Type: {$event->type}\n";
        $body .= "Time: {$event->occurredAt->format('Y-m-d H:i:s T')}\n\n";
        $body .= "Message:\n{$event->message}\n\n";

        if (!empty($event->context)) {
            $body .= "Context:\n";
            foreach ($event->context as $key => $value) {
                $stringValue = is_scalar($value) ? (string) $value : json_encode($value);
                $body .= "  {$key}: {$stringValue}\n";
            }
        }

        try {
            $this->mailer->compose()
                ->setFrom($this->fromEmail)
                ->setTo($this->recipientEmail)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (Throwable $e) {
            Yii::warning("Failed to send email alert: {$e->getMessage()}", 'alerts');
        }
    }

    public function supports(string $severity): bool
    {
        return in_array($severity, $this->supportedSeverities, true);
    }
}
