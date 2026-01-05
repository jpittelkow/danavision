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
     * Includes all known stores from KNOWN_STORE_TEMPLATES for comprehensive coverage.
     * Users can suppress stores they don't want via Settings > Stores.
     *
     * @return array
     */
    protected function getDefaultStores(): array
    {
        return [
            // ============================================
            // GENERAL RETAILERS
            // ============================================
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'domain' => 'amazon.com',
                'search_url_template' => 'https://www.amazon.com/s?k={query}',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 100,
            ],
            [
                'name' => 'Walmart',
                'slug' => 'walmart',
                'domain' => 'walmart.com',
                'search_url_template' => 'https://www.walmart.com/search?q={query}',
                'is_default' => true,
                'is_local' => true, // Local stock availability
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 95,
            ],
            [
                'name' => 'Target',
                'slug' => 'target',
                'domain' => 'target.com',
                'search_url_template' => 'https://www.target.com/s?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 90,
            ],
            [
                'name' => 'eBay',
                'slug' => 'ebay',
                'domain' => 'ebay.com',
                'search_url_template' => 'https://www.ebay.com/sch/i.html?_nkw={query}&LH_BIN=1',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 70,
            ],
            [
                'name' => 'Dollar General',
                'slug' => 'dollar-general',
                'domain' => 'dollargeneral.com',
                'search_url_template' => 'https://www.dollargeneral.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 50,
            ],
            [
                'name' => 'Dollar Tree',
                'slug' => 'dollar-tree',
                'domain' => 'dollartree.com',
                'search_url_template' => 'https://www.dollartree.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 48,
            ],
            [
                'name' => 'Five Below',
                'slug' => 'five-below',
                'domain' => 'fivebelow.com',
                'search_url_template' => 'https://www.fivebelow.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 46,
            ],
            [
                'name' => 'Big Lots',
                'slug' => 'big-lots',
                'domain' => 'biglots.com',
                'search_url_template' => 'https://www.biglots.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GENERAL,
                'default_priority' => 45,
            ],

            // ============================================
            // ELECTRONICS
            // ============================================
            [
                'name' => 'Best Buy',
                'slug' => 'bestbuy',
                'domain' => 'bestbuy.com',
                'search_url_template' => 'https://www.bestbuy.com/site/searchpage.jsp?st={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 85,
            ],
            [
                'name' => 'Newegg',
                'slug' => 'newegg',
                'domain' => 'newegg.com',
                'search_url_template' => 'https://www.newegg.com/p/pl?d={query}',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 75,
            ],
            [
                'name' => 'B&H Photo',
                'slug' => 'bhphoto',
                'domain' => 'bhphotovideo.com',
                'search_url_template' => 'https://www.bhphotovideo.com/c/search?q={query}',
                'is_default' => true,
                'is_local' => false,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 72,
            ],
            [
                'name' => 'Micro Center',
                'slug' => 'micro-center',
                'domain' => 'microcenter.com',
                'search_url_template' => 'https://www.microcenter.com/search/search_results.aspx?N=&Ntt={query}',
                'is_default' => true,
                'is_local' => true, // Store-specific pricing
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 70,
            ],
            [
                'name' => 'GameStop',
                'slug' => 'gamestop',
                'domain' => 'gamestop.com',
                'search_url_template' => 'https://www.gamestop.com/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_ELECTRONICS,
                'default_priority' => 65,
            ],

            // ============================================
            // WAREHOUSE CLUBS
            // ============================================
            [
                'name' => 'Costco',
                'slug' => 'costco',
                'domain' => 'costco.com',
                'search_url_template' => 'https://www.costco.com/CatalogSearch?keyword={query}',
                'is_default' => true,
                'is_local' => true, // Warehouse-specific pricing
                'is_active' => true,
                'category' => Store::CATEGORY_WAREHOUSE,
                'default_priority' => 88,
            ],
            [
                'name' => "Sam's Club",
                'slug' => 'sams-club',
                'domain' => 'samsclub.com',
                'search_url_template' => 'https://www.samsclub.com/s/{query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_WAREHOUSE,
                'default_priority' => 82,
            ],
            [
                'name' => "BJ's Wholesale",
                'slug' => 'bjs',
                'domain' => 'bjs.com',
                'search_url_template' => 'https://www.bjs.com/search/{query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_WAREHOUSE,
                'default_priority' => 80,
            ],

            // ============================================
            // GROCERY - NATIONAL CHAINS
            // ============================================
            [
                'name' => 'Kroger',
                'slug' => 'kroger',
                'domain' => 'kroger.com',
                'search_url_template' => 'https://www.kroger.com/search?query={query}&searchType=default_search',
                'is_default' => true,
                'is_local' => true, // Local pricing
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 75,
            ],
            [
                'name' => 'Albertsons',
                'slug' => 'albertsons',
                'domain' => 'albertsons.com',
                'search_url_template' => 'https://www.albertsons.com/shop/search-results.html?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 73,
            ],
            [
                'name' => 'Safeway',
                'slug' => 'safeway',
                'domain' => 'safeway.com',
                'search_url_template' => 'https://www.safeway.com/shop/search-results.html?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 72,
            ],
            [
                'name' => 'Publix',
                'slug' => 'publix',
                'domain' => 'publix.com',
                'search_url_template' => 'https://www.publix.com/search?query={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 71,
            ],
            [
                'name' => 'H-E-B',
                'slug' => 'heb',
                'domain' => 'heb.com',
                'search_url_template' => 'https://www.heb.com/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 70,
            ],
            [
                'name' => 'Meijer',
                'slug' => 'meijer',
                'domain' => 'meijer.com',
                'search_url_template' => 'https://www.meijer.com/search.html?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 69,
            ],
            [
                'name' => 'Aldi',
                'slug' => 'aldi',
                'domain' => 'aldi.us',
                'search_url_template' => 'https://www.aldi.us/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 68,
            ],
            [
                'name' => 'Lidl',
                'slug' => 'lidl',
                'domain' => 'lidl.com',
                'search_url_template' => 'https://www.lidl.com/search?query={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 67,
            ],
            [
                'name' => "Trader Joe's",
                'slug' => 'trader-joes',
                'domain' => 'traderjoes.com',
                'search_url_template' => 'https://www.traderjoes.com/home/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 66,
            ],
            [
                'name' => 'Whole Foods',
                'slug' => 'whole-foods',
                'domain' => 'wholefoodsmarket.com',
                'search_url_template' => 'https://www.wholefoodsmarket.com/search?text={query}',
                'is_default' => true,
                'is_local' => true, // Prime member pricing varies
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 65,
            ],
            [
                'name' => 'Sprouts',
                'slug' => 'sprouts',
                'domain' => 'sprouts.com',
                'search_url_template' => 'https://www.sprouts.com/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 64,
            ],
            [
                'name' => 'Food Lion',
                'slug' => 'food-lion',
                'domain' => 'foodlion.com',
                'search_url_template' => 'https://www.foodlion.com/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 63,
            ],
            [
                'name' => 'Giant Food',
                'slug' => 'giant-food',
                'domain' => 'giantfood.com',
                'search_url_template' => 'https://giantfood.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 62,
            ],
            [
                'name' => 'Stop & Shop',
                'slug' => 'stop-and-shop',
                'domain' => 'stopandshop.com',
                'search_url_template' => 'https://stopandshop.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 61,
            ],
            [
                'name' => 'Wegmans',
                'slug' => 'wegmans',
                'domain' => 'wegmans.com',
                'search_url_template' => 'https://www.wegmans.com/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_GROCERY,
                'default_priority' => 60,
            ],

            // ============================================
            // HOME & HARDWARE
            // ============================================
            [
                'name' => 'Home Depot',
                'slug' => 'home-depot',
                'domain' => 'homedepot.com',
                'search_url_template' => 'https://www.homedepot.com/s/{query}',
                'is_default' => true,
                'is_local' => true, // Local pricing
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 80,
            ],
            [
                'name' => "Lowe's",
                'slug' => 'lowes',
                'domain' => 'lowes.com',
                'search_url_template' => 'https://www.lowes.com/search?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 78,
            ],
            [
                'name' => 'Menards',
                'slug' => 'menards',
                'domain' => 'menards.com',
                'search_url_template' => 'https://www.menards.com/main/search.html?search={query}',
                'is_default' => true,
                'is_local' => true, // Regional pricing
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 76,
            ],
            [
                'name' => 'Ace Hardware',
                'slug' => 'ace-hardware',
                'domain' => 'acehardware.com',
                'search_url_template' => 'https://www.acehardware.com/search?query={query}',
                'is_default' => true,
                'is_local' => true, // Franchise pricing varies
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 74,
            ],
            [
                'name' => 'True Value',
                'slug' => 'true-value',
                'domain' => 'truevalue.com',
                'search_url_template' => 'https://www.truevalue.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 72,
            ],
            [
                'name' => 'Harbor Freight',
                'slug' => 'harbor-freight',
                'domain' => 'harborfreight.com',
                'search_url_template' => 'https://www.harborfreight.com/catalogsearch/result?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 70,
            ],
            [
                'name' => 'Wayfair',
                'slug' => 'wayfair',
                'domain' => 'wayfair.com',
                'search_url_template' => 'https://www.wayfair.com/keyword.html?keyword={query}',
                'is_default' => true,
                'is_local' => false, // Online only
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 68,
            ],
            [
                'name' => 'IKEA',
                'slug' => 'ikea',
                'domain' => 'ikea.com',
                'search_url_template' => 'https://www.ikea.com/us/en/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 66,
            ],
            [
                'name' => 'Bed Bath & Beyond',
                'slug' => 'bed-bath-beyond',
                'domain' => 'bedbathandbeyond.com',
                'search_url_template' => 'https://www.bedbathandbeyond.com/store/s/{query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_HOME,
                'default_priority' => 64,
            ],

            // ============================================
            // PHARMACY
            // ============================================
            [
                'name' => 'CVS',
                'slug' => 'cvs',
                'domain' => 'cvs.com',
                'search_url_template' => 'https://www.cvs.com/search?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PHARMACY,
                'default_priority' => 65,
            ],
            [
                'name' => 'Walgreens',
                'slug' => 'walgreens',
                'domain' => 'walgreens.com',
                'search_url_template' => 'https://www.walgreens.com/search/results.jsp?Ntt={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PHARMACY,
                'default_priority' => 63,
            ],
            [
                'name' => 'Rite Aid',
                'slug' => 'rite-aid',
                'domain' => 'riteaid.com',
                'search_url_template' => 'https://www.riteaid.com/shop/catalogsearch/result?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PHARMACY,
                'default_priority' => 61,
            ],

            // ============================================
            // PET STORES
            // ============================================
            [
                'name' => 'Petco',
                'slug' => 'petco',
                'domain' => 'petco.com',
                'search_url_template' => 'https://www.petco.com/shop/en/petcostore/search/{query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PET,
                'default_priority' => 70,
            ],
            [
                'name' => 'PetSmart',
                'slug' => 'petsmart',
                'domain' => 'petsmart.com',
                'search_url_template' => 'https://www.petsmart.com/search/?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_PET,
                'default_priority' => 68,
            ],
            [
                'name' => 'Chewy',
                'slug' => 'chewy',
                'domain' => 'chewy.com',
                'search_url_template' => 'https://www.chewy.com/s?query={query}',
                'is_default' => true,
                'is_local' => false, // Online only
                'is_active' => true,
                'category' => Store::CATEGORY_PET,
                'default_priority' => 66,
            ],

            // ============================================
            // CLOTHING & DEPARTMENT STORES
            // ============================================
            [
                'name' => "Kohl's",
                'slug' => 'kohls',
                'domain' => 'kohls.com',
                'search_url_template' => 'https://www.kohls.com/search.jsp?search={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 65,
            ],
            [
                'name' => 'JCPenney',
                'slug' => 'jcpenney',
                'domain' => 'jcpenney.com',
                'search_url_template' => 'https://www.jcpenney.com/s/{query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 63,
            ],
            [
                'name' => "Macy's",
                'slug' => 'macys',
                'domain' => 'macys.com',
                'search_url_template' => 'https://www.macys.com/shop/featured/{query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 61,
            ],
            [
                'name' => 'Nordstrom',
                'slug' => 'nordstrom',
                'domain' => 'nordstrom.com',
                'search_url_template' => 'https://www.nordstrom.com/sr?keyword={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 59,
            ],
            [
                'name' => 'TJ Maxx',
                'slug' => 'tj-maxx',
                'domain' => 'tjmaxx.tjx.com',
                'search_url_template' => 'https://tjmaxx.tjx.com/store/jump/search?q={query}',
                'is_default' => true,
                'is_local' => true, // Store inventory varies greatly
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 57,
            ],
            [
                'name' => 'Ross',
                'slug' => 'ross',
                'domain' => 'rossstores.com',
                'search_url_template' => 'https://www.rossstores.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 55,
            ],
            [
                'name' => 'Old Navy',
                'slug' => 'old-navy',
                'domain' => 'oldnavy.gap.com',
                'search_url_template' => 'https://oldnavy.gap.com/browse/search.do?searchText={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_CLOTHING,
                'default_priority' => 53,
            ],

            // ============================================
            // OFFICE SUPPLIES
            // ============================================
            [
                'name' => 'Staples',
                'slug' => 'staples',
                'domain' => 'staples.com',
                'search_url_template' => 'https://www.staples.com/search?query={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 60,
            ],
            [
                'name' => 'Office Depot',
                'slug' => 'office-depot',
                'domain' => 'officedepot.com',
                'search_url_template' => 'https://www.officedepot.com/catalog/search.do?Ntt={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 58,
            ],

            // ============================================
            // BEAUTY
            // ============================================
            [
                'name' => 'Ulta',
                'slug' => 'ulta',
                'domain' => 'ulta.com',
                'search_url_template' => 'https://www.ulta.com/search?query={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 55,
            ],
            [
                'name' => 'Sephora',
                'slug' => 'sephora',
                'domain' => 'sephora.com',
                'search_url_template' => 'https://www.sephora.com/search?keyword={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 53,
            ],
            [
                'name' => 'Sally Beauty',
                'slug' => 'sally-beauty',
                'domain' => 'sallybeauty.com',
                'search_url_template' => 'https://www.sallybeauty.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 51,
            ],

            // ============================================
            // SPORTING GOODS
            // ============================================
            [
                'name' => "Dick's Sporting Goods",
                'slug' => 'dicks',
                'domain' => 'dickssportinggoods.com',
                'search_url_template' => 'https://www.dickssportinggoods.com/search/SearchDisplay?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 65,
            ],
            [
                'name' => 'Academy Sports',
                'slug' => 'academy',
                'domain' => 'academy.com',
                'search_url_template' => 'https://www.academy.com/search?query={query}',
                'is_default' => true,
                'is_local' => true, // Regional pricing
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 63,
            ],
            [
                'name' => 'REI',
                'slug' => 'rei',
                'domain' => 'rei.com',
                'search_url_template' => 'https://www.rei.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 61,
            ],
            [
                'name' => "Cabela's",
                'slug' => 'cabelas',
                'domain' => 'cabelas.com',
                'search_url_template' => 'https://www.cabelas.com/shop/en/SearchDisplay?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 59,
            ],
            [
                'name' => 'Bass Pro Shops',
                'slug' => 'bass-pro',
                'domain' => 'basspro.com',
                'search_url_template' => 'https://www.basspro.com/shop/en/SearchDisplay?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 57,
            ],

            // ============================================
            // AUTO PARTS
            // ============================================
            [
                'name' => 'AutoZone',
                'slug' => 'autozone',
                'domain' => 'autozone.com',
                'search_url_template' => 'https://www.autozone.com/searchresult?searchText={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 65,
            ],
            [
                'name' => "O'Reilly Auto Parts",
                'slug' => 'oreilly',
                'domain' => 'oreillyauto.com',
                'search_url_template' => 'https://www.oreillyauto.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 63,
            ],
            [
                'name' => 'Advance Auto Parts',
                'slug' => 'advance-auto',
                'domain' => 'advanceautoparts.com',
                'search_url_template' => 'https://shop.advanceautoparts.com/web/SearchResults?searchTerm={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 61,
            ],
            [
                'name' => 'NAPA Auto Parts',
                'slug' => 'napa',
                'domain' => 'napaonline.com',
                'search_url_template' => 'https://www.napaonline.com/en/search?text={query}',
                'is_default' => true,
                'is_local' => true, // Franchise pricing varies
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 59,
            ],

            // ============================================
            // CRAFT & HOBBY
            // ============================================
            [
                'name' => 'Michaels',
                'slug' => 'michaels',
                'domain' => 'michaels.com',
                'search_url_template' => 'https://www.michaels.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 55,
            ],
            [
                'name' => 'JOANN',
                'slug' => 'joann',
                'domain' => 'joann.com',
                'search_url_template' => 'https://www.joann.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 53,
            ],
            [
                'name' => 'Hobby Lobby',
                'slug' => 'hobby-lobby',
                'domain' => 'hobbylobby.com',
                'search_url_template' => 'https://www.hobbylobby.com/search?q={query}',
                'is_default' => true,
                'is_local' => true,
                'is_active' => true,
                'category' => Store::CATEGORY_SPECIALTY,
                'default_priority' => 51,
            ],
        ];
    }
}
