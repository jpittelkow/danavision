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
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('product_query')->nullable();
            $table->string('product_image_url', 2048)->nullable();
            $table->string('product_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('target_price', 10, 2)->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('lowest_price', 10, 2)->nullable();
            $table->decimal('highest_price', 10, 2)->nullable();
            $table->string('current_retailer')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_purchased')->default(false);
            $table->timestamp('purchased_at')->nullable();
            $table->decimal('purchased_price', 10, 2)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['shopping_list_id', 'is_purchased']);
            $table->index(['current_price', 'previous_price']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
