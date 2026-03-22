<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_vendor_prices', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('unit_price', 10, 4)->nullable();
            $table->decimal('unit_quantity', 10, 4)->nullable();
            $table->string('unit_type', 20)->nullable();
            $table->string('package_size', 100)->nullable();
            $table->index('store_id');
            $table->index('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('item_vendor_prices', function (Blueprint $table) {
            // Drop explicit indexes first, then foreign key (MySQL ordering requirement)
            $table->dropIndex(['unit_price']);
            $table->dropIndex(['store_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['store_id', 'unit_price', 'unit_quantity', 'unit_type', 'package_size']);
        });
    }
};
