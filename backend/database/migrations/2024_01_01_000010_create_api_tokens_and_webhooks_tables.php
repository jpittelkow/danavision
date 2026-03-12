<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->json('abilities')->nullable(); // ['*'] for full access
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('token');
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('events'); // ['user.created', 'backup.completed']
            $table->boolean('active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            
            $table->index('active');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->onDelete('cascade');
            $table->string('event');
            $table->json('payload');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamps();
            
            $table->index(['webhook_id', 'created_at']);
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_tokens');
    }
};
