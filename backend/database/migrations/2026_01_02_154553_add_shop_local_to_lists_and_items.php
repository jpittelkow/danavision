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
        // Add shop_local to shopping_lists table (default false)
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->boolean('shop_local')->default(false)->after('threshold_percent');
        });

        // Add shop_local to list_items table (nullable, inherits from list if null)
        Schema::table('list_items', function (Blueprint $table) {
            $table->boolean('shop_local')->nullable()->after('is_purchased');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropColumn('shop_local');
        });

        Schema::table('list_items', function (Blueprint $table) {
            $table->dropColumn('shop_local');
        });
    }
};
