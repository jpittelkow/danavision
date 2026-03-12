<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix existing app_name and app_url records so they are returned by
     * the public settings API (used for page titles, branding, etc).
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->where('group', 'general')
            ->whereIn('key', ['app_name', 'app_url'])
            ->update(['is_public' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->where('group', 'general')
            ->whereIn('key', ['app_name', 'app_url'])
            ->update(['is_public' => false]);
    }
};
