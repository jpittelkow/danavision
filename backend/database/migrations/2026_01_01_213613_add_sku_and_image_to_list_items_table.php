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
        Schema::table('list_items', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('product_url');
            $table->string('uploaded_image_path')->nullable()->after('product_image_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('list_items', function (Blueprint $table) {
            $table->dropColumn(['sku', 'uploaded_image_path']);
        });
    }
};
