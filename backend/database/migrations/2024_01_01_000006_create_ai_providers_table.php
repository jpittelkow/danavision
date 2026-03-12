<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // claude, openai, gemini, ollama, bedrock, azure
            $table->text('api_key')->nullable();
            $table->string('model')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
