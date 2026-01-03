<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShoppingListController;
use App\Http\Controllers\Api\ListItemController;
use App\Http\Controllers\Api\ListShareController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'app' => config('app.name'),
    ]);
});

// Public routes (rate limited: 10/minute)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Protected routes (rate limited: 60/minute)
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/price-drops', [DashboardController::class, 'priceDrops']);
    Route::get('/dashboard/activity', [DashboardController::class, 'activity']);

    // Search
    Route::post('/search', [SearchController::class, 'search']);
    Route::post('/search/image', [SearchController::class, 'imageSearch']);
    Route::get('/search/history', [SearchController::class, 'history']);
    Route::delete('/search/history/{id}', [SearchController::class, 'deleteHistory']);
    Route::post('/search/ai-recommend', [SearchController::class, 'aiRecommend']);

    // Shopping Lists
    Route::get('/lists', [ShoppingListController::class, 'index']);
    Route::post('/lists', [ShoppingListController::class, 'store']);
    Route::get('/lists/{list}', [ShoppingListController::class, 'show']);
    Route::patch('/lists/{list}', [ShoppingListController::class, 'update']);
    Route::delete('/lists/{list}', [ShoppingListController::class, 'destroy']);
    Route::post('/lists/{list}/refresh', [ShoppingListController::class, 'refresh']);
    Route::get('/lists/{list}/price-history', [ShoppingListController::class, 'priceHistory']);

    // List Items
    Route::get('/lists/{list}/items', [ListItemController::class, 'index']);
    Route::post('/lists/{list}/items', [ListItemController::class, 'store']);
    Route::patch('/items/{item}', [ListItemController::class, 'update']);
    Route::delete('/items/{item}', [ListItemController::class, 'destroy']);
    Route::post('/items/{item}/refresh', [ListItemController::class, 'refresh']);
    Route::post('/items/{item}/purchased', [ListItemController::class, 'markPurchased']);
    Route::get('/items/{item}/history', [ListItemController::class, 'history']);

    // List Sharing
    Route::get('/lists/{list}/shares', [ListShareController::class, 'index']);
    Route::post('/lists/{list}/shares', [ListShareController::class, 'store']);
    Route::patch('/shares/{share}', [ListShareController::class, 'update']);
    Route::delete('/shares/{share}', [ListShareController::class, 'destroy']);
    Route::get('/shares/pending', [ListShareController::class, 'pending']);
    Route::post('/shares/{share}/accept', [ListShareController::class, 'accept']);
    Route::post('/shares/{share}/decline', [ListShareController::class, 'decline']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::patch('/settings', [SettingController::class, 'update']);
    Route::get('/settings/ai', [SettingController::class, 'aiSettings']);
    Route::post('/settings/ai/test', [SettingController::class, 'testAi']);
    Route::get('/settings/price-api', [SettingController::class, 'priceApiSettings']);
    Route::post('/settings/price-api/test', [SettingController::class, 'testPriceApi']);
    Route::get('/settings/email', [SettingController::class, 'emailSettings']);
    Route::post('/settings/email/test', [SettingController::class, 'testEmail']);
});
