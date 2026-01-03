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
        Schema::create('local_store_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('zip_code', 20);
            $table->string('store_name');
            $table->string('store_type', 50)->default('retail'); // supermarket, pharmacy, electronics, retail, warehouse
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->timestamp('discovered_at');
            $table->timestamps();

            // Indexes for efficient lookups
            $table->index(['user_id', 'zip_code']);
            $table->index(['user_id', 'zip_code', 'store_type']);
            $table->index('discovered_at');
            
            // Unique constraint to prevent duplicates
            $table->unique(['user_id', 'zip_code', 'store_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_store_caches');
    }
};
