<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Request DTO for adding members to an industry.
 */
final readonly class AddMembersRequest
{
    /**
     * @param string[] $tickers
     */
    public function __construct(
        public int $industryId,
        public array $tickers,
        public string $actorUsername,
    ) {
    }
}
