<?php

declare(strict_types=1);

namespace app\dto\industryconfig;

/**
 * Response DTO for a list of industry configs.
 */
final readonly class IndustryConfigListResponse
{
    /**
     * @param IndustryConfigResponse[] $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (IndustryConfigResponse $item): array => $item->toArray(),
                $this->items
            ),
            'total' => $this->total,
        ];
    }
}
