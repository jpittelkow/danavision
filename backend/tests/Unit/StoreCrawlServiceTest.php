<?php

use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\Store;
use App\Models\User;
use App\Services\Crawler\CrawlAIService;
use App\Services\LLM\LLMOrchestrator;
use App\Services\PriceSearch\UnitPriceNormalizer;
use App\Services\SettingService;
use App\Services\Shopping\StoreCrawlService;

describe('StoreCrawlService', function () {
    beforeEach(function () {
        $this->crawlAI = Mockery::mock(CrawlAIService::class);
        $this->llm = Mockery::mock(LLMOrchestrator::class);
        $this->normalizer = Mockery::mock(UnitPriceNormalizer::class);
        $this->settingService = app(SettingService::class);

        $this->service = new StoreCrawlService(
            $this->crawlAI,
            $this->llm,
            $this->normalizer,
            $this->settingService,
        );
    });

    it('returns empty stats when store has no products to crawl', function () {
        $store = Store::create([
            'name' => 'Empty Store',
            'slug' => 'empty-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
        ]);

        $stats = $this->service->crawlStore($store);

        expect($stats['products_checked'])->toBe(0);
        expect($stats['prices_updated'])->toBe(0);
        expect($stats['errors'])->toBe(0);
        expect($store->refresh()->last_crawled_at)->not->toBeNull();
    });

    it('uses CSS extraction when scrape_instructions are present', function () {
        $user = User::factory()->create();
        $list = new ShoppingList(['name' => 'Test List']);
        $list->user_id = $user->id;
        $list->save();
        $store = Store::create([
            'name' => 'CSS Store',
            'slug' => 'css-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
            'scrape_instructions' => [
                'price_selector' => '.product-price',
                'name_selector' => '.product-title',
                'container_selector' => '.product-list',
            ],
        ]);
        $item = ListItem::create([
            'shopping_list_id' => $list->id,
            'product_name' => 'Whole Milk',
            'product_query' => 'whole milk',
            'is_purchased' => false,
        ]);
        ItemVendorPrice::create([
            'list_item_id' => $item->id,
            'store_id' => $store->id,
            'vendor' => 'CSS Store',
            'current_price' => 3.99,
        ]);

        // CSS extraction returns content, so LLM is called to structure it
        $this->crawlAI->shouldReceive('scrapeWithCssExtraction')
            ->once()
            ->andReturn([
                'success' => true,
                'content' => 'Whole Milk - $3.49',
                'html' => '<span>$3.49</span>',
            ]);

        $this->llm->shouldReceive('query')
            ->once()
            ->andReturn([
                'success' => true,
                'response' => json_encode([
                    ['product_name' => 'Whole Milk 1 Gallon', 'price' => 3.49, 'in_stock' => true, 'image_url' => '', 'url' => 'https://example.com/milk', 'package_size' => '1 gal'],
                ]),
            ]);

        $this->normalizer->shouldReceive('normalize')
            ->once()
            ->andReturn([
                'unit_price' => 3.49,
                'unit_quantity' => 1,
                'unit_type' => 'gal',
                'package_size' => '1 gal',
            ]);

        $stats = $this->service->crawlStore($store);

        expect($stats['products_checked'])->toBe(1);
        expect($stats['prices_updated'])->toBe(1);
        expect($stats['errors'])->toBe(0);
    });

    it('falls back to basic scrape when CSS extraction returns empty', function () {
        $user = User::factory()->create();
        $list = new ShoppingList(['name' => 'Test List']);
        $list->user_id = $user->id;
        $list->save();
        $store = Store::create([
            'name' => 'Fallback Store',
            'slug' => 'fallback-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
            'scrape_instructions' => [
                'price_selector' => '.stale-selector',
                'container_selector' => '.stale-container',
            ],
        ]);
        $item = ListItem::create([
            'shopping_list_id' => $list->id,
            'product_name' => 'Eggs',
            'product_query' => 'eggs',
            'is_purchased' => false,
        ]);
        ItemVendorPrice::create([
            'list_item_id' => $item->id,
            'store_id' => $store->id,
            'vendor' => 'Fallback Store',
            'current_price' => 5.99,
        ]);

        // CSS extraction returns empty content
        $this->crawlAI->shouldReceive('scrapeWithCssExtraction')
            ->once()
            ->andReturn(['success' => true, 'content' => '']);

        // Falls back to basic scrape
        $this->crawlAI->shouldReceive('scrapeUrl')
            ->once()
            ->andReturn([
                'success' => true,
                'content' => 'Eggs Large 12ct - $4.99',
                'html' => '<div>Eggs $4.99</div>',
            ]);

        $this->llm->shouldReceive('query')
            ->once()
            ->andReturn([
                'success' => true,
                'response' => json_encode([
                    ['product_name' => 'Large Eggs 12ct', 'price' => 4.99, 'in_stock' => true, 'image_url' => '', 'url' => 'https://example.com/eggs', 'package_size' => '12 ct'],
                ]),
            ]);

        $this->normalizer->shouldReceive('normalize')
            ->once()
            ->andReturn([
                'unit_price' => 0.42,
                'unit_quantity' => 12,
                'unit_type' => 'ct',
                'package_size' => '12 ct',
            ]);

        $stats = $this->service->crawlStore($store);

        expect($stats['products_checked'])->toBe(1);
        expect($stats['prices_updated'])->toBe(1);
    });

    it('respects max products per store limit', function () {
        $this->settingService->set('price_search', 'store_crawl_max_products_per_store', 1);

        $user = User::factory()->create();
        $list = new ShoppingList(['name' => 'Test List']);
        $list->user_id = $user->id;
        $list->save();
        $store = Store::create([
            'name' => 'Limited Store',
            'slug' => 'limited-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
        ]);

        // Create two items at this store
        $item1 = ListItem::create([
            'shopping_list_id' => $list->id,
            'product_name' => 'Milk',
            'product_query' => 'milk',
            'is_purchased' => false,
        ]);
        $item2 = ListItem::create([
            'shopping_list_id' => $list->id,
            'product_name' => 'Bread',
            'product_query' => 'bread',
            'is_purchased' => false,
        ]);
        ItemVendorPrice::create([
            'list_item_id' => $item1->id,
            'store_id' => $store->id,
            'vendor' => 'Limited Store',
            'current_price' => 3.99,
            'last_checked_at' => now()->subDay(),
        ]);
        ItemVendorPrice::create([
            'list_item_id' => $item2->id,
            'store_id' => $store->id,
            'vendor' => 'Limited Store',
            'current_price' => 2.99,
            'last_checked_at' => now(),
        ]);

        // Only 1 product should be crawled due to limit
        $products = $this->service->getProductsToCrawl($store->id, 1);

        expect($products)->toHaveCount(1);
        // Should be the oldest-checked item
        expect($products->first()->list_item_id)->toBe($item1->id);
    });

    it('updates last_crawled_at after crawling', function () {
        $store = Store::create([
            'name' => 'Timestamp Store',
            'slug' => 'timestamp-store',
            'category' => 'grocery',
            'is_active' => true,
            'search_url_template' => 'https://example.com/search?q={query}',
            'last_crawled_at' => null,
        ]);

        $this->service->crawlStore($store);

        expect($store->refresh()->last_crawled_at)->not->toBeNull();
    });
});
