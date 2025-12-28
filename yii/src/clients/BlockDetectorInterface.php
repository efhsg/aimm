<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\FetchResult;

/**
 * Detects soft blocks (CAPTCHA, JS challenges) in page content.
 */
interface BlockDetectorInterface
{
    /**
     * Analyze response content to detect soft blocks.
     */
    public function detect(FetchResult $result): BlockReason;

    /**
     * Check if the block reason is recoverable (retry may help).
     */
    public function isRecoverable(BlockReason $reason): bool;
}
