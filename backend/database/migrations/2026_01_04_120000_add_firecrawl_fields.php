<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add Firecrawl integration fields.
 * 
 * Adds tracking fields to item_vendor_prices for Firecrawl-sourced data
 * and enables tracking of how/when prices were discovered.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_vendor_prices', function (Blueprint $table) {
            // Track when Firecrawl last crawled this vendor price
            $table->timestamp('last_firecrawl_at')->nullable()->after('last_checked_at');
            
            // Track the source of the price data (initial_discovery, daily_refresh, weekly_discovery)
            $table->string('firecrawl_source')->nullable()->after('last_firecrawl_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_vendor_prices', function (Blueprint $table) {
            $table->dropColumn(['last_firecrawl_at', 'firecrawl_source']);
        });
    }
};
