# Route Definition Pattern

Standard route structure for API endpoints.

## Usage

```php
// backend/routes/api.php

use App\Http\Controllers\Api\ExampleController;

// Public routes (no auth)
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('examples', ExampleController::class);
    Route::post('/examples/{example}/process', [ExampleController::class, 'process']);

    Route::prefix('settings')->group(function () {
        Route::get('/example', [SettingController::class, 'getExample']);
        Route::put('/example', [SettingController::class, 'updateExample']);
    });
});

// Admin-only routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
});
```

**Key files:** `backend/routes/api.php`

**Related:** [Controller](controller.md), [Anti-patterns: Architecture](../anti-patterns/architecture.md#dont-add-admin-routes-without-middleware)
