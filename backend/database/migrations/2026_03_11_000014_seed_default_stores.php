<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $stores = [
            [
                'name' => 'Amazon',
                'domain' => 'amazon.com',
                'search_url_template' => 'https://www.amazon.com/s?k={query}',
                'is_local' => false,
                'category' => 'general',
                'default_priority' => 1,
            ],
            [
                'name' => 'Walmart',
                'domain' => 'walmart.com',
                'search_url_template' => 'https://www.walmart.com/search?q={query}',
                'is_local' => false,
                'category' => 'general',
                'default_priority' => 2,
            ],
            [
                'name' => 'Target',
                'domain' => 'target.com',
                'search_url_template' => 'https://www.target.com/s?searchTerm={query}',
                'is_local' => false,
                'category' => 'general',
                'default_priority' => 3,
            ],
            [
                'name' => 'Costco',
                'domain' => 'costco.com',
                'search_url_template' => 'https://www.costco.com/CatalogSearch?dept=All&keyword={query}',
                'is_local' => false,
                'category' => 'warehouse',
                'default_priority' => 4,
            ],
            [
                'name' => 'Kroger',
                'domain' => 'kroger.com',
                'search_url_template' => 'https://www.kroger.com/search?query={query}',
                'is_local' => false,
                'category' => 'grocery',
                'default_priority' => 5,
            ],
            [
                'name' => 'Walgreens',
                'domain' => 'walgreens.com',
                'search_url_template' => 'https://www.walgreens.com/search/results.jsp?Ntt={query}',
                'is_local' => false,
                'category' => 'pharmacy',
                'default_priority' => 10,
            ],
            [
                'name' => 'CVS',
                'domain' => 'cvs.com',
                'search_url_template' => 'https://www.cvs.com/search?searchTerm={query}',
                'is_local' => false,
                'category' => 'pharmacy',
                'default_priority' => 11,
            ],
            [
                'name' => 'Best Buy',
                'domain' => 'bestbuy.com',
                'search_url_template' => 'https://www.bestbuy.com/site/searchpage.jsp?st={query}',
                'is_local' => false,
                'category' => 'electronics',
                'default_priority' => 6,
            ],
            [
                'name' => 'Home Depot',
                'domain' => 'homedepot.com',
                'search_url_template' => 'https://www.homedepot.com/s/{query}',
                'is_local' => false,
                'category' => 'home-improvement',
                'default_priority' => 7,
            ],
            [
                'name' => "Lowe's",
                'domain' => 'lowes.com',
                'search_url_template' => 'https://www.lowes.com/search?searchTerm={query}',
                'is_local' => false,
                'category' => 'home-improvement',
                'default_priority' => 8,
            ],
            [
                'name' => 'Whole Foods',
                'domain' => 'wholefoodsmarket.com',
                'search_url_template' => 'https://www.wholefoodsmarket.com/search?text={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 12,
            ],
            [
                'name' => "Trader Joe's",
                'domain' => 'traderjoes.com',
                'search_url_template' => 'https://www.traderjoes.com/home/search?q={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 13,
            ],
            [
                'name' => 'Aldi',
                'domain' => 'aldi.us',
                'search_url_template' => 'https://www.aldi.us/search/?q={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 14,
            ],
            [
                'name' => 'Publix',
                'domain' => 'publix.com',
                'search_url_template' => 'https://www.publix.com/search?query={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 15,
            ],
            [
                'name' => 'Safeway',
                'domain' => 'safeway.com',
                'search_url_template' => 'https://www.safeway.com/shop/search-results.html?q={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 16,
            ],
            [
                'name' => 'H-E-B',
                'domain' => 'heb.com',
                'search_url_template' => 'https://www.heb.com/search/?q={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 17,
            ],
            [
                'name' => 'Meijer',
                'domain' => 'meijer.com',
                'search_url_template' => 'https://www.meijer.com/shopping/search.html?text={query}',
                'is_local' => true,
                'category' => 'grocery',
                'default_priority' => 18,
            ],
            [
                'name' => "Sam's Club",
                'domain' => 'samsclub.com',
                'search_url_template' => 'https://www.samsclub.com/s/{query}',
                'is_local' => false,
                'category' => 'warehouse',
                'default_priority' => 9,
            ],
            [
                'name' => "BJ's Wholesale",
                'domain' => 'bjs.com',
                'search_url_template' => 'https://www.bjs.com/search/{query}',
                'is_local' => false,
                'category' => 'warehouse',
                'default_priority' => 19,
            ],
            [
                'name' => 'Instacart',
                'domain' => 'instacart.com',
                'search_url_template' => 'https://www.instacart.com/store/search/{query}',
                'is_local' => false,
                'category' => 'delivery',
                'default_priority' => 20,
            ],
        ];

        foreach ($stores as $store) {
            DB::table('stores')->insert([
                'name' => $store['name'],
                'slug' => Str::slug($store['name']),
                'domain' => $store['domain'],
                'search_url_template' => $store['search_url_template'],
                'is_default' => true,
                'is_local' => $store['is_local'],
                'is_active' => true,
                'auto_configured' => false,
                'category' => $store['category'],
                'default_priority' => $store['default_priority'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stores')->whereIn('slug', [
            'amazon', 'walmart', 'target', 'costco', 'kroger',
            'walgreens', 'cvs', 'best-buy', 'home-depot', 'lowes',
            'whole-foods', 'trader-joes', 'aldi', 'publix', 'safeway',
            'h-e-b', 'meijer', 'sams-club', 'bjs-wholesale', 'instacart',
        ])->delete();
    }
};
