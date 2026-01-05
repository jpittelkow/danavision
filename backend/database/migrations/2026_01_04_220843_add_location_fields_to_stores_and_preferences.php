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
        // Add parent_store_id to stores table for subsidiary relationships
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_store_id')->nullable()->after('id');
            $table->foreign('parent_store_id')->references('id')->on('stores')->nullOnDelete();
        });

        // Add location_id to user_store_preferences for store-specific location IDs
        Schema::table('user_store_preferences', function (Blueprint $table) {
            $table->string('location_id')->nullable()->after('is_local');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['parent_store_id']);
            $table->dropColumn('parent_store_id');
        });

        Schema::table('user_store_preferences', function (Blueprint $table) {
            $table->dropColumn('location_id');
        });
    }
};
