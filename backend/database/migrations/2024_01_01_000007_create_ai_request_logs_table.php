<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('mode'); // single, aggregation, council
            $table->text('prompt');
            $table->text('response')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};
