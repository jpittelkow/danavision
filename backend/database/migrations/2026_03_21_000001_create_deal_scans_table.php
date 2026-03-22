<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path', 500)->nullable();
            $table->string('scan_type', 50)->default('coupon'); // coupon, circular, flyer
            $table->integer('deals_extracted')->default(0);
            $table->integer('deals_accepted')->default(0);
            $table->integer('deals_dismissed')->default(0);
            $table->string('status', 20)->default('processing'); // processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_scans');
    }
};
