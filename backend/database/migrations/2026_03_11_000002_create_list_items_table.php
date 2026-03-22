<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_query')->nullable();
            $table->string('product_image_url')->nullable();
            $table->text('product_url')->nullable();
            $table->string('sku')->nullable();
            $table->string('upc')->nullable();
            $table->string('uploaded_image_path')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('target_price', 10, 2)->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('lowest_price', 10, 2)->nullable();
            $table->decimal('highest_price', 10, 2)->nullable();
            $table->string('current_retailer')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->string('priority')->default('medium')->nullable();
            $table->boolean('is_purchased')->default(false);
            $table->boolean('shop_local')->nullable(); // null = inherit from list
            $table->boolean('is_generic')->default(false);
            $table->string('unit_of_measure')->nullable(); // lb, oz, kg, gallon, etc.
            $table->timestamp('purchased_at')->nullable();
            $table->decimal('purchased_price', 10, 2)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index('shopping_list_id');
            $table->index('is_purchased');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
