<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_add_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, reviewed, added, dismissed
            $table->json('product_data')->nullable(); // array of 5 product suggestions
            $table->string('source')->nullable(); // image, text
            $table->string('source_query')->nullable();
            $table->string('source_image_path')->nullable();
            $table->foreignId('shopping_list_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('added_item_id')->nullable()->constrained('list_items')->nullOnDelete();
            $table->integer('selected_index')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_add_queue_items');
    }
};
