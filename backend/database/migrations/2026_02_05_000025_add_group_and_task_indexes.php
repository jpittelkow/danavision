<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds indexes for permission checks and group-member queries.
     */
    public function up(): void
    {
        Schema::table('group_permissions', function (Blueprint $table) {
            $table->index('group_id');
        });

        Schema::table('user_group_members', function (Blueprint $table) {
            $table->index('group_id');
        });

        Schema::table('task_runs', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_permissions', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
        });

        Schema::table('user_group_members', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
        });

        Schema::table('task_runs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
