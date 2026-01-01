<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

final readonly class ValuationSnapshotRecord
{
    public function __construct(
        public int $companyId,
        public DateTimeImmutable $snapshotDate,
        public ?float $price,
        public ?float $marketCap,
        public ?float $enterpriseValue,
        public ?int $sharesOutstanding,
        public ?float $trailingPe,
        public ?float $forwardPe,
        public ?float $pegRatio,
        public ?float $priceToBook,
        public ?float $priceToSales,
        public ?float $evToEbitda,
        public ?float $evToRevenue,
        public ?float $dividendYield,
        public ?float $fcfYield,
        public ?float $earningsYield,
        public ?float $netDebtToEbitda,
        public string $retentionTier,
        public string $currency,
        public string $sourceAdapter,
        public DateTimeImmutable $collectedAt,
    ) {
    }
}
