<?php

namespace App\Services\AI;

/**
 * Result object for AI price searches.
 * 
 * Contains both the processed results and the original SERP data
 * for transparency and debugging purposes.
 */
class AIPriceSearchResult implements \JsonSerializable
{
    public function __construct(
        public string $query,
        public array $results,
        public ?float $lowestPrice,
        public ?float $highestPrice,
        public \DateTimeInterface $searchedAt,
        public ?string $error = null,
        public array $providersUsed = [],
        public bool $isGeneric = false,
        public ?string $unitOfMeasure = null,
        public ?array $serpData = null,
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'results' => $this->results,
            'lowest_price' => $this->lowestPrice,
            'highest_price' => $this->highestPrice,
            'searched_at' => $this->searchedAt->format(\DateTimeInterface::ATOM),
            'error' => $this->error,
            'providers_used' => $this->providersUsed,
            'is_generic' => $this->isGeneric,
            'unit_of_measure' => $this->unitOfMeasure,
            'serp_data' => $this->serpData,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get a summary of the SERP data for display.
     */
    public function getSerpDataSummary(): ?array
    {
        if (empty($this->serpData)) {
            return null;
        }

        return [
            'query' => $this->serpData['query'] ?? $this->query,
            'results_count' => $this->serpData['results_count'] ?? count($this->serpData['results'] ?? []),
        ];
    }
}
