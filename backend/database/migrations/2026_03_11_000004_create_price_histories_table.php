<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->string('retailer')->nullable();
            $table->text('url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('upc')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->string('source')->default('manual'); // manual, daily_job, user_refresh, smart_add, firecrawl_discovery, firecrawl_refresh
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['list_item_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
