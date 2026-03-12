<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('endpoint');
            $table->string('endpoint_hash', 64);
            $table->string('p256dh', 512);
            $table->string('auth', 512);
            $table->string('device_name')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
            $table->timestamp('last_used_at')->nullable();

            $table->unique(['user_id', 'endpoint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
