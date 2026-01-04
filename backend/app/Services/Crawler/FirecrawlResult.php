<?php

namespace App\Services\Crawler;

/**
 * FirecrawlResult
 * 
 * Value object representing the result of a Firecrawl API call.
 * Contains the crawled price data and metadata about the operation.
 */
class FirecrawlResult
{
    /**
     * Create a new FirecrawlResult instance.
     *
     * @param bool $success Whether the operation was successful
     * @param array $results The crawled results
     * @param string|null $source The source type (initial_discovery, daily_refresh, weekly_discovery)
     * @param string|null $error Error message if failed
     * @param array $metadata Additional metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $results,
        public readonly ?string $source = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param array $results The crawled results
     * @param string $source The source type
     * @param array $metadata Additional metadata
     * @return self
     */
    public static function success(array $results, string $source, array $metadata = []): self
    {
        return new self(
            success: true,
            results: $results,
            source: $source,
            error: null,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     *
     * @param string $error The error message
     * @param array $metadata Additional metadata
     * @return self
     */
    public static function error(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            results: [],
            source: null,
            error: $error,
            metadata: $metadata,
        );
    }

    /**
     * Check if the operation was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if there was an error.
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return !$this->success || $this->error !== null;
    }

    /**
     * Check if there are any results.
     *
     * @return bool
     */
    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    /**
     * Get the number of results.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->results);
    }

    /**
     * Get the lowest price from results.
     *
     * @return float|null
     */
    public function getLowestPrice(): ?float
    {
        if (empty($this->results)) {
            return null;
        }

        $prices = array_filter(
            array_column($this->results, 'price'),
            fn($p) => $p !== null && $p > 0
        );

        return !empty($prices) ? min($prices) : null;
    }

    /**
     * Get the highest price from results.
     *
     * @return float|null
     */
    public function getHighestPrice(): ?float
    {
        if (empty($this->results)) {
            return null;
        }

        $prices = array_filter(
            array_column($this->results, 'price'),
            fn($p) => $p !== null && $p > 0
        );

        return !empty($prices) ? max($prices) : null;
    }

    /**
     * Get the result with the lowest price.
     *
     * @return array|null
     */
    public function getBestDeal(): ?array
    {
        if (empty($this->results)) {
            return null;
        }

        $lowest = null;
        $lowestPrice = PHP_INT_MAX;

        foreach ($this->results as $result) {
            $price = $result['price'] ?? PHP_INT_MAX;
            if ($price > 0 && $price < $lowestPrice) {
                $lowest = $result;
                $lowestPrice = $price;
            }
        }

        return $lowest;
    }

    /**
     * Get results filtered to in-stock items only.
     *
     * @return array
     */
    public function getInStockResults(): array
    {
        return array_filter(
            $this->results,
            fn($r) => ($r['stock_status'] ?? 'in_stock') !== 'out_of_stock'
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'results' => $this->results,
            'source' => $this->source,
            'error' => $this->error,
            'metadata' => $this->metadata,
            'count' => $this->count(),
            'lowest_price' => $this->getLowestPrice(),
            'highest_price' => $this->getHighestPrice(),
        ];
    }
}
