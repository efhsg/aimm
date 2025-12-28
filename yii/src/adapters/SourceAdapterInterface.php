<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;

/**
 * Maps fetched HTML/JSON responses to structured Extraction DTOs.
 */
interface SourceAdapterInterface
{
    /**
     * Get unique identifier for this adapter.
     */
    public function getAdapterId(): string;

    /**
     * Get supported datapoint keys.
     *
     * @return list<string>
     */
    public function getSupportedKeys(): array;

    /**
     * Adapt fetched content to structured extractions.
     */
    public function adapt(AdaptRequest $request): AdaptResult;
}
