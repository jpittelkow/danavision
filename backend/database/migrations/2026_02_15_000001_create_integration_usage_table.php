<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_usage', function (Blueprint $table) {
            $table->id();
            $table->string('integration'); // llm, email, sms, storage, broadcasting
            $table->string('provider'); // openai, anthropic, twilio, ses, s3, etc.
            $table->string('metric'); // tokens_in, tokens_out, messages, bytes_uploaded, bytes_downloaded, connections
            $table->decimal('quantity', 16, 4);
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['integration', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_usage');
    }
};
