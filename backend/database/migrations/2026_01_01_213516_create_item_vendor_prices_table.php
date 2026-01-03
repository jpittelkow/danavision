<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_vendor_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_item_id')->constrained()->cascadeOnDelete();
            $table->string('vendor'); // Amazon, Walmart, Target, Best Buy, etc.
            $table->string('vendor_sku')->nullable(); // Product SKU at this vendor
            $table->string('product_url')->nullable(); // Direct link to product page
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('lowest_price', 10, 2)->nullable();
            $table->decimal('highest_price', 10, 2)->nullable();
            $table->boolean('on_sale')->default(false);
            $table->decimal('sale_percent_off', 5, 2)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            // Index for efficient queries
            $table->index(['list_item_id', 'vendor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_vendor_prices');
    }
};
