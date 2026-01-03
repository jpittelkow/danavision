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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // claude, openai, gemini, local
            $table->text('api_key')->nullable(); // encrypted
            $table->string('model')->nullable();
            $table->string('base_url')->nullable(); // for Ollama/custom endpoints
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('test_status')->default('untested'); // untested, success, failed
            $table->text('test_error')->nullable();
            $table->timestamps();

            // Each user can only have one provider of each type
            $table->unique(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
