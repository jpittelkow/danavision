<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for common query patterns on audit_logs and access_logs.
     * Supports filter-by-severity (audit) and filter-by-action (access) with date ordering.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['severity', 'created_at']);
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['severity', 'created_at']);
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
        });
    }
};
