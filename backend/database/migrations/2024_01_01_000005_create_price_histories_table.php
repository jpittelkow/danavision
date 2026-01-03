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
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->string('retailer');
            $table->string('url', 2048)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamp('captured_at');
            $table->enum('source', ['manual', 'daily_job', 'user_refresh'])->default('manual');
            $table->timestamps();

            $table->index(['list_item_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
