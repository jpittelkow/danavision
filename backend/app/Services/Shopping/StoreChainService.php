<?php

namespace App\Services\Shopping;

use App\Models\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreChainService
{
    /**
     * Known retail chains with their subsidiaries.
     * Used to auto-link discovered local stores to parent chain search infrastructure.
     */
    public const KNOWN_CHAINS = [
        'kroger' => [
            'domain' => 'kroger.com',
            'template' => 'https://www.kroger.com/search?query={query}&searchType=default_search',
            'subsidiaries' => [
                'metro market', 'pick n save', "pick 'n save",
                "mariano's", 'marianos', 'fred meyer', 'ralphs',
                'king soopers', "fry's", 'frys', "smith's", 'smiths',
                'qfc', 'quality food centers', 'dillons', 'city market',
                "baker's", 'bakers', 'gerbes', 'jay c',
                'food 4 less', 'foods co', 'harris teeter', 'ruler',
            ],
            'location_type' => 'store_id',
        ],
        'albertsons' => [
            'domain' => 'albertsons.com',
            'template' => 'https://www.albertsons.com/shop/search-results.html?q={query}',
            'subsidiaries' => [
                'safeway', 'vons', 'jewel-osco', 'jewel osco',
                'acme', "shaw's", 'shaws', 'star market',
                'randalls', 'tom thumb', 'pavilions', 'carrs', 'haggen',
            ],
            'location_type' => 'store_id',
        ],
        'ahold_delhaize' => [
            'domain' => 'stopandshop.com',
            'template' => 'https://stopandshop.com/search?q={query}',
            'subsidiaries' => [
                'stop & shop', 'stop and shop',
                'giant', 'giant food', 'food lion', 'hannaford',
            ],
            'location_type' => 'store_id',
        ],
    ];

    /**
     * Match a store name against known chain subsidiaries.
     *
     * @return array{chain: string, parent_slug: string, domain: string, template: string}|null
     */
    public function matchChain(string $storeName): ?array
    {
        $normalized = strtolower(trim($storeName));

        foreach (self::KNOWN_CHAINS as $chainKey => $chain) {
            // Check if the store name IS the parent chain
            if ($normalized === $chainKey) {
                return [
                    'chain' => $chainKey,
                    'parent_slug' => $chainKey,
                    'domain' => $chain['domain'],
                    'template' => $chain['template'],
                ];
            }

            // Check against subsidiaries
            foreach ($chain['subsidiaries'] as $subsidiary) {
                if ($normalized === $subsidiary || Str::contains($normalized, $subsidiary)) {
                    return [
                        'chain' => $chainKey,
                        'parent_slug' => $chainKey,
                        'domain' => $chain['domain'],
                        'template' => $chain['template'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Auto-link a store to its parent chain if it matches a known subsidiary.
     * Sets parent_store_id and copies search_url_template if missing.
     */
    public function autoLinkSubsidiary(Store $store): void
    {
        $match = $this->matchChain($store->name);
        if (!$match) {
            return;
        }

        $parent = Store::where('slug', $match['parent_slug'])->first();
        if (!$parent) {
            Log::info('StoreChainService: Parent store not found for chain link', [
                'store' => $store->name,
                'expected_parent_slug' => $match['parent_slug'],
            ]);
            return;
        }

        // Don't link a store to itself
        if ($store->id === $parent->id) {
            return;
        }

        $updates = ['parent_store_id' => $parent->id];

        // Copy search URL template from parent if the subsidiary doesn't have one
        if (empty($store->search_url_template) && !empty($parent->search_url_template)) {
            $updates['search_url_template'] = $parent->search_url_template;
        }

        // Copy domain from parent if missing
        if (empty($store->domain)) {
            $updates['domain'] = $match['domain'];
        }

        $store->update($updates);

        Log::info('StoreChainService: Auto-linked subsidiary to parent', [
            'store' => $store->name,
            'parent' => $parent->name,
            'chain' => $match['chain'],
        ]);
    }

    /**
     * Get all known chain names (parent + subsidiaries) for a given chain key.
     */
    public function getChainNames(string $chainKey): array
    {
        $chain = self::KNOWN_CHAINS[$chainKey] ?? null;
        if (!$chain) {
            return [];
        }

        return array_merge([$chainKey], $chain['subsidiaries']);
    }
}
