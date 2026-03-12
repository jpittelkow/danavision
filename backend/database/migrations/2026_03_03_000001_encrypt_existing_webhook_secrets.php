<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Encrypt existing plaintext webhook secrets.
     */
    public function up(): void
    {
        if (empty(config('app.key'))) {
            Log::warning('Skipping webhook secret encryption: APP_KEY is not set. Re-run this migration after setting APP_KEY.');
            return;
        }

        $webhooks = DB::table('webhooks')
            ->whereNotNull('secret')
            ->where('secret', '!=', '')
            ->get();

        foreach ($webhooks as $webhook) {
            // Skip values that are already encrypted
            try {
                Crypt::decryptString($webhook->secret);
                continue; // Already encrypted, skip
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Not encrypted, proceed to encrypt
            }

            DB::table('webhooks')
                ->where('id', $webhook->id)
                ->update(['secret' => Crypt::encryptString($webhook->secret)]);
        }
    }

    /**
     * Reverse: cannot decrypt without knowing which were originally plaintext.
     */
    public function down(): void
    {
        // Irreversible — encrypted secrets cannot be reliably distinguished
        // from secrets that were already encrypted before this migration.
    }
};
