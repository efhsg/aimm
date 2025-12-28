<?php

declare(strict_types=1);

namespace app\alerts;

/**
 * Alert notifier interface for sending alerts via various channels.
 */
interface AlertNotifierInterface
{
    public function notify(CollectionAlertEvent $event): void;

    public function supports(string $severity): bool;
}
