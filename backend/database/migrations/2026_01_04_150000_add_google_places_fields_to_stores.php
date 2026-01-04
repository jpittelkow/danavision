<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Google Places API fields to the stores table for nearby store discovery.
     * These fields enable:
     * - Deduplication via google_place_id
     * - Distance calculations via lat/lng
     * - Display of store address and phone
     * - Tracking of AI-auto-configured stores
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('domain');
            $table->decimal('latitude', 10, 7)->nullable()->after('google_place_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('address')->nullable()->after('longitude');
            $table->string('phone')->nullable()->after('address');
            $table->boolean('auto_configured')->default(false)->after('is_active');

            // Index for deduplication lookups
            $table->index('google_place_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['google_place_id']);
            $table->dropColumn([
                'google_place_id',
                'latitude',
                'longitude',
                'address',
                'phone',
                'auto_configured',
            ]);
        });
    }
};
