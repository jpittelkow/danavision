<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('group')->default('general');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
