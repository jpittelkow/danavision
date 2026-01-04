<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add 'smart_add' as a valid source for price_histories.
     * This requires rebuilding the table for SQLite (which uses CHECK constraints for enum).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Need to recreate table to modify CHECK constraint
            DB::transaction(function () {
                // Create new table with updated enum
                Schema::create('price_histories_new', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('list_item_id')->constrained()->cascadeOnDelete();
                    $table->decimal('price', 10, 2);
                    $table->string('retailer');
                    $table->string('url', 2048)->nullable();
                    $table->boolean('in_stock')->default(true);
                    $table->timestamp('captured_at');
                    $table->enum('source', ['manual', 'daily_job', 'user_refresh', 'smart_add'])->default('manual');
                    $table->timestamps();

                    $table->index(['list_item_id', 'captured_at']);
                });

                // Copy data
                DB::statement('INSERT INTO price_histories_new SELECT * FROM price_histories');

                // Drop old table
                Schema::drop('price_histories');

                // Rename new table
                Schema::rename('price_histories_new', 'price_histories');
            });
        } else {
            // MySQL/PostgreSQL: Can modify column directly
            Schema::table('price_histories', function (Blueprint $table) {
                $table->enum('source', ['manual', 'daily_job', 'user_refresh', 'smart_add'])
                    ->default('manual')
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::transaction(function () {
                // Create table with original enum
                Schema::create('price_histories_old', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('list_item_id')->constrained()->cascadeOnDelete();
                    $table->decimal('price', 10, 2);
                    $table->string('retailer');
                    $table->string('url', 2048)->nullable();
                    $table->boolean('in_stock')->default(true);
                    $table->timestamp('captured_at');
                    $table->enum('source', ['manual', 'daily_job', 'user_refresh'])->default('manual');
                    $table->timestamps();

                    $table->index(['list_item_id', 'captured_at']);
                });

                // Copy data (convert smart_add to daily_job)
                DB::statement("INSERT INTO price_histories_old 
                    SELECT id, list_item_id, price, retailer, url, in_stock, captured_at,
                        CASE WHEN source = 'smart_add' THEN 'daily_job' ELSE source END,
                        created_at, updated_at
                    FROM price_histories");

                Schema::drop('price_histories');
                Schema::rename('price_histories_old', 'price_histories');
            });
        } else {
            Schema::table('price_histories', function (Blueprint $table) {
                $table->enum('source', ['manual', 'daily_job', 'user_refresh'])
                    ->default('manual')
                    ->change();
            });
        }
    }
};
