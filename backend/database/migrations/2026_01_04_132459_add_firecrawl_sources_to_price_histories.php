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
     * Add 'firecrawl_discovery' and 'firecrawl_refresh' as valid sources for price_histories.
     * This requires rebuilding the table for SQLite (which uses CHECK constraints for enum).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Need to recreate table to modify CHECK constraint
            DB::transaction(function () {
                // Drop temp table and index if they exist from a failed migration
                DB::statement('DROP INDEX IF EXISTS price_histories_new_list_item_id_captured_at_index');
                Schema::dropIfExists('price_histories_new');
                
                // Create new table with updated enum (without auto-named index to avoid conflicts)
                DB::statement("
                    CREATE TABLE price_histories_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        list_item_id INTEGER NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        retailer VARCHAR(255) NOT NULL,
                        url VARCHAR(2048),
                        in_stock INTEGER DEFAULT 1,
                        captured_at DATETIME NOT NULL,
                        source VARCHAR(255) DEFAULT 'manual' CHECK (source IN ('manual', 'daily_job', 'user_refresh', 'smart_add', 'firecrawl_discovery', 'firecrawl_refresh')),
                        created_at DATETIME,
                        updated_at DATETIME,
                        FOREIGN KEY (list_item_id) REFERENCES list_items(id) ON DELETE CASCADE
                    )
                ");

                // Copy data
                DB::statement('INSERT INTO price_histories_new SELECT * FROM price_histories');

                // Drop old table
                Schema::drop('price_histories');

                // Rename new table
                Schema::rename('price_histories_new', 'price_histories');
                
                // Create index on the renamed table
                DB::statement('CREATE INDEX price_histories_list_item_id_captured_at_index ON price_histories (list_item_id, captured_at)');
            });
        } else {
            // MySQL/PostgreSQL: Can modify column directly
            Schema::table('price_histories', function (Blueprint $table) {
                $table->enum('source', [
                    'manual',
                    'daily_job',
                    'user_refresh',
                    'smart_add',
                    'firecrawl_discovery',
                    'firecrawl_refresh',
                ])
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
                // Drop temp table and index if they exist from a failed migration
                DB::statement('DROP INDEX IF EXISTS price_histories_old_list_item_id_captured_at_index');
                Schema::dropIfExists('price_histories_old');
                
                // Create table with previous enum (without firecrawl sources)
                DB::statement("
                    CREATE TABLE price_histories_old (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        list_item_id INTEGER NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        retailer VARCHAR(255) NOT NULL,
                        url VARCHAR(2048),
                        in_stock INTEGER DEFAULT 1,
                        captured_at DATETIME NOT NULL,
                        source VARCHAR(255) DEFAULT 'manual' CHECK (source IN ('manual', 'daily_job', 'user_refresh', 'smart_add')),
                        created_at DATETIME,
                        updated_at DATETIME,
                        FOREIGN KEY (list_item_id) REFERENCES list_items(id) ON DELETE CASCADE
                    )
                ");

                // Copy data (convert firecrawl sources to daily_job)
                DB::statement("INSERT INTO price_histories_old 
                    SELECT id, list_item_id, price, retailer, url, in_stock, captured_at,
                        CASE 
                            WHEN source IN ('firecrawl_discovery', 'firecrawl_refresh') THEN 'daily_job' 
                            ELSE source 
                        END,
                        created_at, updated_at
                    FROM price_histories");

                Schema::drop('price_histories');
                Schema::rename('price_histories_old', 'price_histories');
                
                // Create index on the renamed table
                DB::statement('CREATE INDEX price_histories_list_item_id_captured_at_index ON price_histories (list_item_id, captured_at)');
            });
        } else {
            Schema::table('price_histories', function (Blueprint $table) {
                $table->enum('source', ['manual', 'daily_job', 'user_refresh', 'smart_add'])
                    ->default('manual')
                    ->change();
            });
        }
    }
};
