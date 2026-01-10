<?php

declare(strict_types=1);

namespace app\dto\datapoints;

use app\enums\CollectionMethod;
use app\enums\DataScale;
use DateTimeImmutable;

/**
 * Value object for monetary datapoints (market cap, revenue, prices).
 */
final readonly class DataPointMoney
{
    public const UNIT = 'currency';

    /**
     * @param list<string>|null $attemptedSources
     * @param list<string>|null $derivedFrom
     */
    public function __construct(
        public ?float $value,
        public string $currency,
        public DataScale $scale,
        public DateTimeImmutable $asOf,
        public ?string $sourceUrl,
        public DateTimeImmutable $retrievedAt,
        public CollectionMethod $method,
        public ?SourceLocator $sourceLocator = null,
        public ?array $attemptedSources = null,
        public ?array $derivedFrom = null,
        public ?string $formula = null,
        public ?FxConversion $fxConversion = null,
        public ?string $cacheSource = null,
        public ?int $cacheAgeDays = null,
        public ?string $providerId = null,
    ) {
        $this->validateProvenanceForMethod();
    }

    /**
     * Validate that method-specific provenance requirements are met.
     *
     * @throws \InvalidArgumentException If required provenance fields are missing
     */
    private function validateProvenanceForMethod(): void
    {
        match ($this->method) {
            CollectionMethod::WebFetch,
            CollectionMethod::WebSearch,
            CollectionMethod::Api => $this->validateFetchProvenance(),
            CollectionMethod::NotFound => $this->validateNotFoundProvenance(),
            CollectionMethod::Derived => $this->validateDerivedProvenance(),
            CollectionMethod::Cache => $this->validateCacheProvenance(),
        };
    }

    private function validateFetchProvenance(): void
    {
        if ($this->sourceUrl === null || $this->sourceUrl === '') {
            throw new \InvalidArgumentException(
                "Method {$this->method->value} requires sourceUrl"
            );
        }
        if ($this->sourceLocator === null) {
            throw new \InvalidArgumentException(
                "Method {$this->method->value} requires sourceLocator"
            );
        }
    }

    private function validateNotFoundProvenance(): void
    {
        if ($this->attemptedSources === null || $this->attemptedSources === []) {
            throw new \InvalidArgumentException(
                'Method not_found requires attemptedSources'
            );
        }
    }

    private function validateDerivedProvenance(): void
    {
        if ($this->derivedFrom === null || $this->derivedFrom === []) {
            throw new \InvalidArgumentException(
                'Method derived requires derivedFrom'
            );
        }
        if ($this->formula === null || $this->formula === '') {
            throw new \InvalidArgumentException(
                'Method derived requires formula'
            );
        }
    }

    private function validateCacheProvenance(): void
    {
        if ($this->cacheSource === null || $this->cacheSource === '') {
            throw new \InvalidArgumentException(
                'Method cache requires cacheSource'
            );
        }
        if ($this->cacheAgeDays === null) {
            throw new \InvalidArgumentException(
                'Method cache requires cacheAgeDays'
            );
        }
    }

    /**
     * Get value in base units (not thousands/millions/billions).
     */
    public function getBaseValue(): ?float
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->scale) {
            DataScale::Units => $this->value,
            DataScale::Thousands => $this->value * 1_000,
            DataScale::Millions => $this->value * 1_000_000,
            DataScale::Billions => $this->value * 1_000_000_000,
            DataScale::Trillions => $this->value * 1_000_000_000_000,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'unit' => self::UNIT,
            'currency' => $this->currency,
            'scale' => $this->scale->value,
            'as_of' => $this->asOf->format('Y-m-d'),
            'source_url' => $this->sourceUrl,
            'retrieved_at' => $this->retrievedAt->format(DateTimeImmutable::ATOM),
            'method' => $this->method->value,
            'source_locator' => $this->sourceLocator?->toArray(),
            'attempted_sources' => $this->attemptedSources,
            'derived_from' => $this->derivedFrom,
            'formula' => $this->formula,
            'fx_conversion' => $this->fxConversion?->toArray(),
            'cache_source' => $this->cacheSource,
            'cache_age_days' => $this->cacheAgeDays,
            'provider_id' => $this->providerId,
        ];
    }
}
