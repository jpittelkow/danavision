<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Add local_stock and local_price flags to stores
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('local_stock')->default(false)->after('is_active');
            $table->boolean('local_price')->default(false)->after('local_stock');
        });

        // Backfill flags on existing stores
        $this->backfillStoreFlags();

        // Seed Menards
        $this->seedMenards();
    }

    private function backfillStoreFlags(): void
    {
        // Both local_stock and local_price = true (prices vary by location)
        DB::table('stores')
            ->whereIn('slug', ['kroger', 'costco', 'sams-club'])
            ->update(['local_stock' => true, 'local_price' => true]);

        // local_stock = true, local_price = false (national pricing, local availability)
        DB::table('stores')
            ->whereIn('slug', [
                'walmart', 'target', 'aldi', 'trader-joes',
                'best-buy', 'home-depot', 'lowes',
                'walgreens', 'cvs',
                'whole-foods', 'publix', 'safeway', 'h-e-b', 'meijer',
                'bjs-wholesale',
            ])
            ->update(['local_stock' => true, 'local_price' => false]);

        // Amazon and Instacart: both false (online only)
        DB::table('stores')
            ->whereIn('slug', ['amazon', 'instacart'])
            ->update(['local_stock' => false, 'local_price' => false]);
    }

    private function seedMenards(): void
    {
        $now = now();

        // Check if Menards already exists
        if (DB::table('stores')->where('slug', 'menards')->exists()) {
            return;
        }

        DB::table('stores')->insert([
            'name' => 'Menards',
            'slug' => 'menards',
            'domain' => 'menards.com',
            'search_url_template' => 'https://www.menards.com/main/search.html?search={query}',
            'is_default' => true,
            'is_local' => true,
            'is_active' => true,
            'auto_configured' => false,
            'local_stock' => true,
            'local_price' => true,
            'category' => 'home-improvement',
            'default_priority' => 9,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Remove Menards
        DB::table('stores')->where('slug', 'menards')->where('is_default', true)->delete();

        // Remove columns
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['local_stock', 'local_price']);
        });
    }
};
