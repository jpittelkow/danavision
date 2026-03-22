<?php

namespace App\Services\PriceSearch\Providers;

interface PriceProviderInterface
{
    /**
     * Search for products matching the query.
     *
     * @param string $query The search query
     * @param array $options Additional search options (e.g., location, filters)
     * @return array Array of product results with keys: product_name, price, retailer, url, in_stock, image_url
     */
    public function search(string $query, array $options = []): array;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Check if the provider is available (API key configured, etc.).
     */
    public function isAvailable(): bool;
}
