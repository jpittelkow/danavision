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
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('user_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('group_id')->constrained('user_groups')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'group_id']);
        });

        Schema::create('group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('user_groups')->onDelete('cascade');
            $table->string('permission', 100);
            $table->string('resource_type', 50)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamps();
            $table->index('permission');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_permissions');
        Schema::dropIfExists('user_group_members');
        Schema::dropIfExists('user_groups');
    }
};
