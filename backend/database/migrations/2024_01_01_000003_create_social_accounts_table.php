<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // google, github, microsoft, etc.
            $table->string('provider_id');
            $table->text('provider_token')->nullable();
            $table->text('provider_refresh_token')->nullable();
            $table->timestamp('provider_token_expires_at')->nullable();
            $table->string('avatar')->nullable();
            $table->string('nickname')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
