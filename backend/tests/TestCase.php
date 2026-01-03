<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    
    protected $enableCsrfMiddleware = false;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        if (! $this->enableCsrfMiddleware) {
            $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        }
    }
}
