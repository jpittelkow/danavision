<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enable strict mode in development
        Model::shouldBeStrict(! $this->app->isProduction());

        // Prevent lazy loading in development
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
