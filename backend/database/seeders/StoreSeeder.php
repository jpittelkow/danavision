<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * StoreSeeder
 *
 * Seeds the database with default stores and their URL templates.
 * This enables cost-effective price discovery without using the
 * expensive Firecrawl Agent API for common retailers.
 */
class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = $this->getDefaultStores();

        foreach ($stores as $storeData) {
            Store::firstOrCreate(
                ['slug' => $storeData['slug']],
                $storeData
            );
        }

        $this->command->info('Seeded ' . count($stores) . ' default stores.');
    }

    /**
     * Get the default store configurations.
     *
     * @return array
     */
    protected function getDefaultStores(): array
    {
        return [
            // Major General Retailers
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'domain' => 'amazon.com',
                'search_url_template' => 'https://www.amazon.com/s?k={query}',
                'product_url_pattern' => '/\\/dp\\/[A-Z0-9]+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 100,
                'scrape_instructions' => [
                    'price_selectors' => ['.a-price-whole', '.a-offscreen', '#priceblock_ourprice'],
                    'title_selectors' => ['#productTitle', '.product-title-word-break'],
                    'hints' => 'Look for the main product price, not "was" prices. Extract from span.a-price or #priceblock elements.',
                ],
            ],
            [
                'name' => 'Walmart',
                'slug' => 'walmart',
                'domain' => 'walmart.com',
                'search_url_template' => 'https://www.walmart.com/search?q={query}',
                'product_url_pattern' => '/\\/ip\\/[^\\?]+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 95,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-testid="price-wrap"]', '.price-characteristic'],
                    'hints' => 'Main price is in the price-characteristic element. Watch for pickup vs delivery prices.',
                ],
            ],
            [
                'name' => 'Target',
                'slug' => 'target',
                'domain' => 'target.com',
                'search_url_template' => 'https://www.target.com/s?searchTerm={query}',
                'product_url_pattern' => '/\\/p\\/[^\\?]+\\/-\\/A-\\d+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 90,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-test="product-price"]', '.styles__CurrentPriceFontSize'],
                    'hints' => 'Price is in data-test="product-price" element. May have different prices for shipping vs store pickup.',
                ],
            ],
            [
                'name' => 'eBay',
                'slug' => 'ebay',
                'domain' => 'ebay.com',
                'search_url_template' => 'https://www.ebay.com/sch/i.html?_nkw={query}&LH_BIN=1',
                'product_url_pattern' => '/\\/itm\\/\\d+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 70,
                'scrape_instructions' => [
                    'price_selectors' => ['.x-price-primary', '[itemprop="price"]'],
                    'hints' => 'Filter for Buy It Now prices. Skip auctions unless specifically requested.',
                ],
            ],

            // Electronics Retailers
            [
                'name' => 'Best Buy',
                'slug' => 'bestbuy',
                'domain' => 'bestbuy.com',
                'search_url_template' => 'https://www.bestbuy.com/site/searchpage.jsp?st={query}',
                'product_url_pattern' => '/\\/site\\/[^\\/]+\\/\\d+\\.p/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 85,
                'scrape_instructions' => [
                    'price_selectors' => ['.priceView-customer-price span', '[data-testid="customer-price"]'],
                    'hints' => 'Main price is in priceView-customer-price. Check for member pricing separately.',
                ],
            ],
            [
                'name' => 'Newegg',
                'slug' => 'newegg',
                'domain' => 'newegg.com',
                'search_url_template' => 'https://www.newegg.com/p/pl?d={query}',
                'product_url_pattern' => '/\\/p\\/[A-Z0-9\\-]+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 75,
                'scrape_instructions' => [
                    'price_selectors' => ['.price-current', '[itemprop="price"]'],
                    'hints' => 'Price is in price-current class. Check for combo deals.',
                ],
            ],
            [
                'name' => 'B&H Photo',
                'slug' => 'bhphoto',
                'domain' => 'bhphotovideo.com',
                'search_url_template' => 'https://www.bhphotovideo.com/c/search?Ntt={query}&N=0',
                'product_url_pattern' => '/\\/c\\/product\\/\\d+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 72,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-selenium="pricingPrice"]', '.price_'],
                    'hints' => 'Professional camera and electronics retailer. Prices are usually competitive.',
                ],
            ],

            // Warehouse Clubs
            [
                'name' => 'Costco',
                'slug' => 'costco',
                'domain' => 'costco.com',
                'search_url_template' => 'https://www.costco.com/CatalogSearch?dept=All&keyword={query}',
                'product_url_pattern' => '/\\.product\\.\\d+\\.html/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_WAREHOUSE,
                'default_priority' => 88,
                'scrape_instructions' => [
                    'price_selectors' => ['.price', '[automation-id="productPriceOutput"]'],
                    'hints' => 'Membership required for purchase. Prices shown are member prices.',
                ],
            ],
            [
                'name' => "Sam's Club",
                'slug' => 'sams-club',
                'domain' => 'samsclub.com',
                'search_url_template' => 'https://www.samsclub.com/s/{query}',
                'product_url_pattern' => '/\\/p\\/[^\\?]+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_WAREHOUSE,
                'default_priority' => 82,
                'scrape_instructions' => [
                    'price_selectors' => ['.Price-characteristic', '[data-testid="price"]'],
                    'hints' => 'Membership required. Prices are for Plus members by default.',
                ],
            ],

            // Home Improvement
            [
                'name' => 'Home Depot',
                'slug' => 'home-depot',
                'domain' => 'homedepot.com',
                'search_url_template' => 'https://www.homedepot.com/s/{query}',
                'product_url_pattern' => '/\\/p\\/[^\\/]+\\/\\d+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 80,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-testid="price-format"]', '.price-format__main-price'],
                    'hints' => 'Check for per-unit pricing on bulk items. Local inventory may vary.',
                ],
            ],
            [
                'name' => "Lowe's",
                'slug' => 'lowes',
                'domain' => 'lowes.com',
                'search_url_template' => 'https://www.lowes.com/search?searchTerm={query}',
                'product_url_pattern' => '/\\/pd\\/[^\\/]+\\/\\d+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 78,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-testid="product-price"]', '.aPrice'],
                    'hints' => 'Similar to Home Depot. Check for MyLowes member pricing.',
                ],
            ],

            // Grocery Stores
            [
                'name' => 'Kroger',
                'slug' => 'kroger',
                'domain' => 'kroger.com',
                'search_url_template' => 'https://www.kroger.com/search?query={query}',
                'product_url_pattern' => '/\\/p\\/[^\\/]+\\/\\d+/',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 75,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-testid="cart-page-item-unit-price"]', '.kds-Price'],
                    'hints' => 'Regional grocery chain. Prices vary by location. Check for digital coupons.',
                ],
            ],
            [
                'name' => 'Publix',
                'slug' => 'publix',
                'domain' => 'publix.com',
                'search_url_template' => 'https://www.publix.com/shop/search/{query}',
                'product_url_pattern' => '/\\/pd\\/[^\\/]+\\//',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 73,
                'scrape_instructions' => [
                    'price_selectors' => ['.price', '[data-testid="product-price"]'],
                    'hints' => 'Southeast US grocery chain. Check for BOGO deals.',
                ],
            ],
            [
                'name' => 'Safeway',
                'slug' => 'safeway',
                'domain' => 'safeway.com',
                'search_url_template' => 'https://www.safeway.com/shop/search-results.html?q={query}',
                'product_url_pattern' => '/\\/shop\\/product-details\\.\\d+\\.html/',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 72,
                'scrape_instructions' => [
                    'price_selectors' => ['.product-price', '[data-testid="price"]'],
                    'hints' => 'Western US grocery chain. Part of Albertsons family. Digital coupons available.',
                ],
            ],
            [
                'name' => 'Whole Foods',
                'slug' => 'whole-foods',
                'domain' => 'wholefoodsmarket.com',
                'search_url_template' => 'https://www.amazon.com/s?k={query}&i=wholefoods',
                'product_url_pattern' => '/\\/dp\\/[A-Z0-9]+/',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 70,
                'scrape_instructions' => [
                    'price_selectors' => ['.a-price-whole', '.a-offscreen'],
                    'hints' => 'Uses Amazon infrastructure. Prime members get discounts.',
                ],
            ],

            // Pharmacy/Health
            [
                'name' => 'CVS',
                'slug' => 'cvs',
                'domain' => 'cvs.com',
                'search_url_template' => 'https://www.cvs.com/search?searchTerm={query}',
                'product_url_pattern' => '/\\/shop\\/[^\\/]+\\/[^\\/]+-prodid-\\d+/',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PHARMACY,
                'default_priority' => 65,
                'scrape_instructions' => [
                    'price_selectors' => ['.price-wrapper', '[data-testid="regular-price"]'],
                    'hints' => 'Check for ExtraCare member pricing. Often has coupons.',
                ],
            ],
            [
                'name' => 'Walgreens',
                'slug' => 'walgreens',
                'domain' => 'walgreens.com',
                'search_url_template' => 'https://www.walgreens.com/search/results.jsp?Ntt={query}',
                'product_url_pattern' => '/\\/store\\/c\\/[^\\/]+\\/ID=[a-zA-Z0-9\\-]+/',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PHARMACY,
                'default_priority' => 63,
                'scrape_instructions' => [
                    'price_selectors' => ['#regular-price', '.product__price'],
                    'hints' => 'Check for myWalgreens member pricing. BOGO deals common.',
                ],
            ],

            // Specialty Retailers
            [
                'name' => 'Wayfair',
                'slug' => 'wayfair',
                'domain' => 'wayfair.com',
                'search_url_template' => 'https://www.wayfair.com/keyword.html?keyword={query}',
                'product_url_pattern' => '/\\/.+-[A-Z0-9]+\\.html/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 68,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-enzyme-id="PriceBlock"]', '.BasePriceBlock'],
                    'hints' => 'Home goods and furniture. Check for flash sales.',
                ],
            ],
            [
                'name' => 'Chewy',
                'slug' => 'chewy',
                'domain' => 'chewy.com',
                'search_url_template' => 'https://www.chewy.com/s?query={query}',
                'product_url_pattern' => '/\\/dp\\/\\d+/',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 65,
                'scrape_instructions' => [
                    'price_selectors' => ['[data-testid="price"]', '.Price'],
                    'hints' => 'Pet supplies. Autoship prices are lower than one-time purchase.',
                ],
            ],
        ];
    }
}
