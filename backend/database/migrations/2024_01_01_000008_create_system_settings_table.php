<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false); // Visible to non-admins
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['group', 'key']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
