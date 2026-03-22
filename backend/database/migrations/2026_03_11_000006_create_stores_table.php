<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->foreignId('parent_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('google_place_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->text('search_url_template')->nullable();
            $table->string('product_url_pattern')->nullable();
            $table->json('scrape_instructions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_local')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_configured')->default(false);
            $table->string('logo_url')->nullable();
            $table->string('category')->nullable();
            $table->integer('default_priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
