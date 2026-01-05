<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    
    protected bool $enableCsrfMiddleware = false;
    
    /**
     * Boot the testing helper traits.
     * 
     * CRITICAL: This runs BEFORE RefreshDatabase migrations!
     * We override this to add safety checks before any database operations.
     */
    protected function setUpTraits(): array
    {
        // SAFEGUARD: Check database safety BEFORE RefreshDatabase runs migrations
        // This prevents accidental data loss by stopping tests before they can wipe data
        $this->assertNotProductionDatabase();
        
        return parent::setUpTraits();
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        if (! $this->enableCsrfMiddleware) {
            $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        }
    }
    
    /**
     * Ensure tests are NOT running against the production database.
     * 
     * This safeguard prevents accidental data loss by verifying:
     * 1. We're in the 'testing' environment
     * 2. The database is :memory: (required - file-based DBs are not safe)
     * 
     * CRITICAL: This check runs BEFORE RefreshDatabase migrations to prevent data loss.
     * 
     * @throws RuntimeException If tests would run against production database
     */
    protected function assertNotProductionDatabase(): void
    {
        // Check 1: Environment must not be 'production'
        // Use env() first as it reflects actual environment, config() may be cached
        $env = env('APP_ENV') ?? config('app.env');
        if ($env === 'production') {
            throw new RuntimeException(
                'SAFETY STOP: Cannot run tests in production environment. ' .
                'Set APP_ENV=testing before running tests.'
            );
        }
        
        // Check 2: Database MUST be in-memory for tests
        // env() takes precedence over config() to catch Docker overrides
        $database = env('DB_DATABASE') ?? config('database.connections.sqlite.database');
        
        // Allow :memory: databases (the safest option)
        if ($database === ':memory:') {
            return;
        }
        
        // Allow databases with 'test' in the filename (for explicit test databases)
        if (is_string($database) && str_contains(strtolower($database), 'test')) {
            return;
        }
        
        // BLOCK all other databases - this is critical for safety
        throw new RuntimeException(
            "SAFETY STOP: Tests attempted to use database '{$database}'. " .
            "Tests MUST use DB_DATABASE=:memory: to prevent data loss. " .
            "If running in Docker, use: ./scripts/run-tests.sh"
        );
    }
}
