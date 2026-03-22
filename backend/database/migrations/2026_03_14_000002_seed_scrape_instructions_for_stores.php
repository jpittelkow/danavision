<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stores')->where('slug', 'walmart')->update([
            'scrape_instructions' => json_encode([
                'container_selector' => '[data-testid="item-stack"] [data-item-id]',
                'name_selector' => '[data-automation-id="product-title"]',
                'price_selector' => '[data-automation-id="product-price"] .f2',
                'image_selector' => '[data-testid="productTileImage"] img',
                'link_selector' => 'a[link-identifier]',
                'in_stock_indicator' => '[data-automation-id="add-to-cart"]',
                'package_size_selector' => '[data-automation-id="product-variant"]',
                'wait_for' => '[data-testid="item-stack"]',
                'js_only' => true,
            ]),
        ]);

        DB::table('stores')->where('slug', 'kroger')->update([
            'scrape_instructions' => json_encode([
                'container_selector' => '.AutoGrid-cell [data-testid="product-card"]',
                'name_selector' => '[data-testid="cart-page-item-description"] a, .kds-Text--l',
                'price_selector' => '[data-testid="cart-page-item-unit-price"], .kds-Price-promotional, .kds-Price',
                'image_selector' => '.kds-Image-img',
                'link_selector' => '[data-testid="cart-page-item-description"] a',
                'in_stock_indicator' => '[data-testid="cart-page-item-add-to-cart"]',
                'package_size_selector' => '.kds-Text--s.kds-Text--regular',
                'wait_for' => '.AutoGrid-cell',
                'js_only' => true,
            ]),
        ]);

        DB::table('stores')->where('slug', 'target')->update([
            'scrape_instructions' => json_encode([
                'container_selector' => '[data-test="product-grid"] [data-test="@web/site-top-of-funnel/ProductCardWrapper"]',
                'name_selector' => '[data-test="product-title"] a',
                'price_selector' => '[data-test="current-price"] span',
                'image_selector' => '[data-test="product-image"] picture img',
                'link_selector' => '[data-test="product-title"] a',
                'in_stock_indicator' => '[data-test="shippingButton"], [data-test="pickupButton"]',
                'package_size_selector' => '[data-test="product-sub-title"]',
                'wait_for' => '[data-test="product-grid"]',
                'js_only' => true,
            ]),
        ]);
    }

    public function down(): void
    {
        DB::table('stores')
            ->whereIn('slug', ['walmart', 'kroger', 'target'])
            ->update(['scrape_instructions' => null]);
    }
};
