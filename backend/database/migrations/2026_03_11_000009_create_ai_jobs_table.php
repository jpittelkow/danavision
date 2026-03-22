<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // product_identification, image_analysis, price_search, smart_fill, price_refresh, firecrawl_discovery, firecrawl_refresh, nearby_store_discovery, store_auto_config
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('progress')->default(0);
            $table->foreignId('related_item_id')->nullable()->constrained('list_items')->nullOnDelete();
            $table->foreignId('related_list_id')->nullable()->constrained('shopping_lists')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};
