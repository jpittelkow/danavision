<?php

namespace App\Services\PriceApi;

interface PriceProviderInterface
{
    /**
     * Search for products.
     *
     * @param string $query Search query
     * @param array $options Additional options
     * @return array Array of price results
     */
    public function search(string $query, array $options = []): array;

    /**
     * Check if the provider is configured.
     */
    public function isConfigured(): bool;

    /**
     * Test the connection to the provider.
     */
    public function testConnection(): bool;
}
