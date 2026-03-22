<?php

namespace App\Services\PriceSearch;

use App\Models\Store;
use App\Services\Shopping\StoreChainService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VendorNameResolver
{
    /**
     * Cache duration for store lookups (1 hour).
     */
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly StoreChainService $storeChainService,
    ) {}

    /**
     * Resolve a free-text vendor name and optional URL to a Store model.
     *
     * Resolution order:
     * 1. Exact slug match
     * 2. Domain match from product URL
     * 3. Fuzzy name match
     * 4. Chain/subsidiary resolution
     *
     * @return Store|null The matched store, or null if no match found
     */
    public function resolve(string $vendorName, ?string $productUrl = null): ?Store
    {
        if (empty(trim($vendorName)) && empty($productUrl)) {
            return null;
        }

        // 1. Try exact slug match
        $slug = Str::slug($vendorName);
        $store = $this->findBySlug($slug);
        if ($store) {
            return $store;
        }

        // 2. Try domain match from URL
        if ($productUrl) {
            $store = $this->findByUrl($productUrl);
            if ($store) {
                return $store;
            }
        }

        // 3. Try fuzzy name match
        $store = $this->findByName($vendorName);
        if ($store) {
            return $store;
        }

        // 4. Try chain/subsidiary resolution
        return $this->findByChain($vendorName);
    }

    /**
     * Resolve a vendor name to a store_id. Convenience wrapper around resolve().
     */
    public function resolveToId(string $vendorName, ?string $productUrl = null): ?int
    {
        return $this->resolve($vendorName, $productUrl)?->id;
    }

    /**
     * Find store by slug.
     */
    private function findBySlug(string $slug): ?Store
    {
        if (empty($slug)) {
            return null;
        }

        $key = "vendor_resolve:slug:{$slug}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $store = Store::where('slug', $slug)->where('is_active', true)->first();
        if ($store) {
            Cache::put($key, $store, self::CACHE_TTL);
        }

        return $store;
    }

    /**
     * Find store by domain extracted from URL.
     */
    private function findByUrl(string $url): ?Store
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $domain = preg_replace('/^www\./', '', strtolower($host));

        $key = "vendor_resolve:domain:{$domain}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Try exact domain match
        $store = Store::where('domain', $domain)->where('is_active', true)->first();

        if (!$store) {
            // Try partial domain match (e.g., "grocery.walmart.com" → "walmart.com")
            $parts = explode('.', $domain);
            if (count($parts) > 2) {
                $baseDomain = implode('.', array_slice($parts, -2));
                $store = Store::where('domain', $baseDomain)->where('is_active', true)->first();
            }
        }

        if ($store) {
            Cache::put($key, $store, self::CACHE_TTL);
        }

        return $store;
    }

    /**
     * Find store by fuzzy name matching.
     */
    private function findByName(string $vendorName): ?Store
    {
        $normalized = strtolower(trim($vendorName));

        // Strip common suffixes like "(Brand Name)" that Kroger API returns
        $cleaned = preg_replace('/\s*\(.*?\)\s*$/', '', $normalized) ?? $normalized;
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);

        $key = "vendor_resolve:name:{$cleaned}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Try exact name match (case-insensitive)
        $store = Store::whereRaw('LOWER(name) = ?', [$cleaned])->where('is_active', true)->first();

        if (!$store) {
            // Try "starts with" match for names like "Walmart Supercenter" → "Walmart"
            $store = Store::whereRaw('LOWER(name) LIKE ?', [$cleaned . '%'])->where('is_active', true)->first();
        }

        if (!$store) {
            // Try "contains" match — vendor name contains the store name
            $stores = Store::where('is_active', true)->get();
            foreach ($stores as $s) {
                $storeLower = strtolower($s->name);
                if (Str::contains($cleaned, $storeLower) || Str::contains($storeLower, $cleaned)) {
                    $store = $s;
                    break;
                }
            }
        }

        if ($store) {
            Cache::put($key, $store, self::CACHE_TTL);
        }

        return $store;
    }

    /**
     * Find store via chain/subsidiary matching.
     */
    private function findByChain(string $vendorName): ?Store
    {
        $match = $this->storeChainService->matchChain($vendorName);
        if (!$match) {
            return null;
        }

        $key = "vendor_resolve:chain:{$match['parent_slug']}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $store = Store::where('slug', $match['parent_slug'])->where('is_active', true)->first();
        if ($store) {
            Cache::put($key, $store, self::CACHE_TTL);
        }

        return $store;
    }

    /**
     * Clear the resolver cache. Useful after stores are added/removed.
     */
    public function clearCache(): void
    {
        // Since we use individual keys, we'd need to track them.
        // For now, rely on TTL expiry. A full flush can be done via:
        // Cache::flush() or specific key patterns if using a tagged driver.
    }
}
