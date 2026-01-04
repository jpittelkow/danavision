<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the stores table for the Store Registry system.
     * Stores contain URL templates for efficient price discovery
     * without using the expensive Firecrawl Agent API.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // "Amazon", "Walmart", "Target"
            $table->string('slug')->unique();                    // "amazon", "walmart", "target"
            $table->string('domain');                            // "amazon.com", "walmart.com"
            $table->string('search_url_template')->nullable();   // "https://amazon.com/s?k={query}"
            $table->string('product_url_pattern')->nullable();   // Regex pattern to identify product pages
            $table->json('scrape_instructions')->nullable();     // Store-specific extraction hints for AI
            $table->boolean('is_default')->default(false);       // Pre-populated default stores
            $table->boolean('is_local')->default(false);         // Local/regional store
            $table->boolean('is_active')->default(true);         // Can be disabled globally
            $table->string('logo_url')->nullable();              // Store logo for UI
            $table->string('category')->nullable();              // "general", "electronics", "grocery", etc.
            $table->integer('default_priority')->default(50);    // Default sort priority (higher = first)
            $table->timestamps();

            $table->index('domain');
            $table->index('is_default');
            $table->index('is_active');
        });

        Schema::create('user_store_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->integer('priority')->default(0);             // User's custom priority (higher = first)
            $table->boolean('enabled')->default(true);           // User can disable specific stores
            $table->boolean('is_favorite')->default(false);      // Marked as favorite by user
            $table->timestamps();

            $table->unique(['user_id', 'store_id']);
            $table->index(['user_id', 'enabled']);
            $table->index(['user_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_store_preferences');
        Schema::dropIfExists('stores');
    }
};
