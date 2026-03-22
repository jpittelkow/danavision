<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanned_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_scan_id')->nullable()->constrained('deal_scans')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('store_name_raw', 255)->nullable();
            $table->string('product_name', 255);
            $table->text('product_description')->nullable();
            $table->string('deal_type', 50)->default('coupon'); // coupon, circular, flyer, bogo, clearance
            $table->string('discount_type', 50); // amount_off, percent_off, fixed_price, bogo, buy_x_get_y
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->json('conditions')->nullable(); // {min_purchase, limit_per_customer, requires_loyalty_card, stackable}
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('status', 20)->default('pending'); // pending, active, expired, dismissed
            $table->foreignId('matched_list_item_id')->nullable()->constrained('list_items')->nullOnDelete();
            $table->string('content_hash', 64)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'valid_to']);
            $table->index('content_hash');
            $table->index('matched_list_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanned_deals');
    }
};
