<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove app_url from system_settings.
     *
     * app_url is now controlled exclusively by the APP_URL environment variable
     * and is no longer stored in the database.
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->where('group', 'general')
            ->where('key', 'app_url')
            ->delete();
    }

    /**
     * Reverse the migration (re-insert app_url from current APP_URL env).
     */
    public function down(): void
    {
        $appUrl = config('app.url');

        // Only re-insert if we have a value and the row doesn't already exist
        if ($appUrl && !DB::table('system_settings')->where('group', 'general')->where('key', 'app_url')->exists()) {
            DB::table('system_settings')->insert([
                'group' => 'general',
                'key' => 'app_url',
                'value' => $appUrl,
                'is_encrypted' => false,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
