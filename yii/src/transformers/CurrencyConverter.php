<?php

declare(strict_types=1);

namespace app\transformers;

use app\queries\FxRateQuery;
use DateTimeImmutable;

/**
 * Converts monetary values between currencies using ECB rates.
 *
 * Rates are batch-loaded and cached to avoid N+1 queries.
 */
final class CurrencyConverter
{
    /** @var array<string, float> Cached rates by cache key */
    private array $rateCache = [];

    public function __construct(
        private readonly FxRateQuery $fxRateQuery,
    ) {
    }

    public function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        DateTimeImmutable $asOfDate
    ): float {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getRate($fromCurrency, $toCurrency, $asOfDate);
        return round($amount * $rate, 2);
    }

    /**
     * Batch-convert multiple amounts for efficiency.
     *
     * @param list<array{amount: float, currency: string, date: DateTimeImmutable}> $items
     * @return list<float>
     */
    public function convertBatch(array $items, string $toCurrency): array
    {
        $this->preloadRates($items, $toCurrency);

        return array_map(
            fn (array $item): float => $this->convert(
                (float) $item['amount'],
                (string) $item['currency'],
                $toCurrency,
                $item['date']
            ),
            $items
        );
    }

    public function getRate(
        string $fromCurrency,
        string $toCurrency,
        DateTimeImmutable $asOfDate
    ): float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = $this->cacheKey($fromCurrency, $toCurrency, $asOfDate);
        if (isset($this->rateCache[$cacheKey])) {
            return $this->rateCache[$cacheKey];
        }

        // ECB rates are based on EUR
        if ($fromCurrency === 'EUR') {
            $rate = $this->fxRateQuery->findClosestRate($toCurrency, $asOfDate) ?? 1.0;
        } elseif ($toCurrency === 'EUR') {
            $eurRate = $this->fxRateQuery->findClosestRate($fromCurrency, $asOfDate);
            $rate = ($eurRate !== null && abs($eurRate) > PHP_FLOAT_EPSILON) ? 1 / $eurRate : 1.0;
        } else {
            // Cross rate: FROM -> EUR -> TO
            // Try to find components in cache first (populated by preloadRates)
            $fromEurKey = $this->cacheKey('EUR', $fromCurrency, $asOfDate);
            $toEurKey = $this->cacheKey('EUR', $toCurrency, $asOfDate);

            if (isset($this->rateCache[$fromEurKey])) {
                $fromEur = $this->rateCache[$fromEurKey];
            } else {
                $fromEur = $this->fxRateQuery->findClosestRate($fromCurrency, $asOfDate) ?? 1.0;
            }

            if (isset($this->rateCache[$toEurKey])) {
                $toEur = $this->rateCache[$toEurKey];
            } else {
                $toEur = $this->fxRateQuery->findClosestRate($toCurrency, $asOfDate) ?? 1.0;
            }

            $rate = (abs($fromEur) > PHP_FLOAT_EPSILON) ? $toEur / $fromEur : 1.0;
        }
        $this->rateCache[$cacheKey] = $rate;
        return $rate;
    }

    private function preloadRates(array $items, string $toCurrency): void
    {
        $currencies = array_unique(array_column($items, 'currency'));
        $currencies = array_filter($currencies, fn (string $c): bool => $c !== $toCurrency);

        if (empty($currencies)) {
            return;
        }

        // Load rates for all currencies involved. ECB rates are EUR-based.
        $currencies[] = $toCurrency;
        $currencies = array_unique($currencies);
        $currencies = array_filter($currencies, fn (string $c): bool => $c !== 'EUR');

        if (empty($currencies)) {
            return;
        }

        $dates = array_map(fn ($i) => $i['date'], $items);
        $minDate = min($dates);
        $maxDate = max($dates);

        $rates = $this->fxRateQuery->findRatesInRange($currencies, $minDate, $maxDate);

        // Pre-fill cache with base rates (EUR -> X)
        foreach ($rates as $row) {
            $key = $this->cacheKey('EUR', $row['quote_currency'], new DateTimeImmutable($row['rate_date']));
            $this->rateCache[$key] = (float) $row['rate'];
        }
    }

    private function cacheKey(string $from, string $to, DateTimeImmutable $date): string
    {
        return sprintf('%s_%s_%s', $from, $to, $date->format('Y-m-d'));
    }
}
