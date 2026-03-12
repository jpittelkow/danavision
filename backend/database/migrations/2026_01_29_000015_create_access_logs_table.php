<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('fields_accessed')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
