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
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Job type: product_identification, image_analysis, price_search, smart_fill, price_refresh
            $table->string('type', 50);
            
            // Status: pending, processing, completed, failed, cancelled
            $table->string('status', 20)->default('pending');
            
            // Input/output data as JSON
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            
            // Error tracking
            $table->text('error_message')->nullable();
            
            // Progress tracking (0-100)
            $table->unsignedTinyInteger('progress')->default(0);
            
            // Optional relationships to items/lists
            $table->foreignId('related_item_id')->nullable()->constrained('list_items')->onDelete('set null');
            $table->foreignId('related_list_id')->nullable()->constrained('shopping_lists')->onDelete('set null');
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
    }
};
