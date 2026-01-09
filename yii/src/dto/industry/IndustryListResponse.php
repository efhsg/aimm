<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Response DTO for listing industries.
 */
final readonly class IndustryListResponse
{
    /**
     * @param list<IndustryResponse> $industries
     * @param array{total: int, active: int, inactive: int} $counts
     */
    public function __construct(
        public array $industries,
        public array $counts,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'industries' => array_map(
                fn (IndustryResponse $i): array => $i->toArray(),
                $this->industries
            ),
            'counts' => $this->counts,
        ];
    }
}
