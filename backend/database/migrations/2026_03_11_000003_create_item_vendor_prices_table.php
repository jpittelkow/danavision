<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_vendor_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_item_id')->constrained()->cascadeOnDelete();
            $table->string('vendor');
            $table->string('vendor_sku')->nullable();
            $table->text('product_url')->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('lowest_price', 10, 2)->nullable();
            $table->decimal('highest_price', 10, 2)->nullable();
            $table->boolean('on_sale')->default(false);
            $table->decimal('sale_percent_off', 5, 2)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_firecrawl_at')->nullable();
            $table->string('firecrawl_source')->nullable(); // initial_discovery, daily_refresh, weekly_discovery
            $table->timestamps();
            $table->index('list_item_id');
            $table->index('vendor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_vendor_prices');
    }
};
