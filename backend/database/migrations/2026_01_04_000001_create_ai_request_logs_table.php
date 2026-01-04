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
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Optional link to AI job
            $table->foreignId('ai_job_id')->nullable()->constrained('ai_jobs')->onDelete('cascade');
            
            // Provider info: claude, openai, gemini, local
            $table->string('provider', 30);
            $table->string('model', 100)->nullable();
            
            // Request type: completion, image_analysis, test_connection, price_aggregation
            $table->string('request_type', 50);
            
            // Request and response data
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            
            // Error tracking
            $table->text('error_message')->nullable();
            
            // Token usage
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            
            // Performance tracking
            $table->unsignedInteger('duration_ms')->default(0);
            
            // Status: success, failed, timeout
            $table->string('status', 20)->default('pending');
            
            // SERP API data for price_aggregation requests
            $table->json('serp_data')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for efficient querying
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['ai_job_id']);
            $table->index(['provider', 'created_at']);
            $table->index('request_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};
