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
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 128)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('running'); // running, success, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_runs');
    }
};
