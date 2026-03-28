<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_store_preferences', function (Blueprint $table) {
            $table->string('chain_name')->nullable()->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_store_preferences', function (Blueprint $table) {
            $table->dropColumn('chain_name');
        });
    }
};
