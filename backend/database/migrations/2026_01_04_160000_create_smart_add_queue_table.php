<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create smart_add_queue table for storing pending product identifications.
 * 
 * This table stores products identified by AI that are awaiting user review.
 * Users can review, add to a list, or dismiss items from this queue.
 * Items are automatically cleaned up after 7 days.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smart_add_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Status: pending (awaiting review), reviewed, added (to list), dismissed
            $table->string('status', 20)->default('pending');
            
            // Product data from AI identification (JSON array of suggestions)
            $table->json('product_data');
            
            // Source information
            $table->string('source_type', 20); // 'image' or 'text'
            $table->text('source_query')->nullable(); // Text query if source_type is 'text'
            $table->string('source_image_path')->nullable(); // Stored image path if source_type is 'image'
            
            // Reference to the AI job that produced this result
            $table->foreignId('ai_job_id')->nullable()->constrained('ai_jobs')->onDelete('set null');
            
            // Reference to the item created when user adds to list
            $table->foreignId('added_item_id')->nullable()->constrained('list_items')->onDelete('set null');
            
            // User-selected product index from suggestions (0-4)
            $table->unsignedTinyInteger('selected_index')->nullable();
            
            // Providers used for identification
            $table->json('providers_used')->nullable();
            
            // Auto-cleanup timestamp (default 7 days from creation)
            $table->timestamp('expires_at');
            
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_add_queue');
    }
};
