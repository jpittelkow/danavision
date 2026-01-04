<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AIJobController;
use App\Http\Controllers\AIProviderController;
use App\Http\Controllers\AIRequestLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageProxyController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\SmartAddController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Image proxy (public, no auth required)
Route::get('api/proxy-image', [ImageProxyController::class, 'proxy'])->name('proxy-image');

// Public routes
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Smart Add - Product Identification Flow
    // Phase 1: Identify products (via image or text)
    // Phase 2: Add to list (price search runs as background job)
    Route::get('smart-add', [SmartAddController::class, 'index'])->name('smart-add');
    Route::post('smart-add/identify', [SmartAddController::class, 'identify'])->name('smart-add.identify');
    Route::post('smart-add/add', [SmartAddController::class, 'addToList'])->name('smart-add.add');
    // Legacy endpoints (kept for backward compatibility)
    Route::post('smart-add/analyze', [SmartAddController::class, 'analyzeImage'])->name('smart-add.analyze');
    Route::post('smart-add/search', [SmartAddController::class, 'searchText'])->name('smart-add.search');

    // Shopping Lists
    Route::resource('lists', ShoppingListController::class);
    Route::post('lists/{list}/refresh', [ShoppingListController::class, 'refresh'])->name('lists.refresh');

    // List Items
    Route::post('lists/{list}/items', [ListItemController::class, 'store'])->name('lists.items.store');
    Route::get('items/{item}', [ListItemController::class, 'show'])->name('items.show');
    Route::patch('items/{item}', [ListItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ListItemController::class, 'destroy'])->name('items.destroy');
    Route::post('items/{item}/refresh', [ListItemController::class, 'refresh'])->name('items.refresh');
    Route::post('items/{item}/purchased', [ListItemController::class, 'markPurchased'])->name('items.purchased');
    Route::post('items/{item}/smart-fill', [ListItemController::class, 'smartFill'])->name('items.smart-fill');
    Route::get('api/items/{item}/active-job', [ListItemController::class, 'activeJob'])->name('items.active-job');

    // Search
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search', [SearchController::class, 'search']);
    Route::post('search/image', [SearchController::class, 'imageSearch'])->name('search.image');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings');
    Route::patch('settings', [SettingController::class, 'update']);
    Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');

    // Address lookup (Nominatim proxy)
    Route::get('api/address/search', [AddressController::class, 'search'])->name('address.search');
    Route::get('api/address/reverse', [AddressController::class, 'reverse'])->name('address.reverse');

    // AI Providers (redirect old URL to main settings page)
    Route::get('settings/ai', function () {
        return redirect('/settings?tab=ai');
    })->name('settings.ai');
    Route::post('settings/ai', [AIProviderController::class, 'store'])->name('ai-providers.store');
    Route::patch('ai-providers/{provider}', [AIProviderController::class, 'update'])->name('ai-providers.update');
    Route::delete('ai-providers/{provider}', [AIProviderController::class, 'destroy'])->name('ai-providers.destroy');
    Route::post('ai-providers/{provider}/primary', [AIProviderController::class, 'setPrimary'])->name('ai-providers.primary');
    Route::post('ai-providers/{provider}/toggle', [AIProviderController::class, 'toggleActive'])->name('ai-providers.toggle');
    Route::post('ai-providers/{provider}/test', [AIProviderController::class, 'test'])->name('ai-providers.test');
    Route::get('ai-providers/{provider}/models', [AIProviderController::class, 'fetchModels'])->name('ai-providers.models');
    Route::post('ai-providers/{provider}/models/refresh', [AIProviderController::class, 'refreshModels'])->name('ai-providers.models.refresh');
    Route::get('api/ollama-models', [AIProviderController::class, 'ollamaModels'])->name('ai-providers.ollama-models');

    // AI Prompts
    Route::patch('settings/ai/prompts', [AIProviderController::class, 'updatePrompt'])->name('ai-prompts.update');
    Route::post('settings/ai/prompts/reset', [AIProviderController::class, 'resetPrompt'])->name('ai-prompts.reset');

    // AI Jobs API
    Route::prefix('api/ai-jobs')->group(function () {
        Route::get('/', [AIJobController::class, 'index'])->name('ai-jobs.index');
        Route::get('/active', [AIJobController::class, 'active'])->name('ai-jobs.active');
        Route::get('/stats', [AIJobController::class, 'stats'])->name('ai-jobs.stats');
        Route::post('/', [AIJobController::class, 'store'])->name('ai-jobs.store');
        Route::delete('/history', [AIJobController::class, 'clearHistory'])->name('ai-jobs.clear-history');
        Route::get('/{aiJob}', [AIJobController::class, 'show'])->name('ai-jobs.show');
        Route::post('/{aiJob}/cancel', [AIJobController::class, 'cancel'])->name('ai-jobs.cancel');
        Route::delete('/{aiJob}', [AIJobController::class, 'destroy'])->name('ai-jobs.destroy');
    });

    // AI Request Logs API
    Route::prefix('api/ai-logs')->group(function () {
        Route::get('/', [AIRequestLogController::class, 'index'])->name('ai-logs.index');
        Route::get('/stats', [AIRequestLogController::class, 'stats'])->name('ai-logs.stats');
        Route::delete('/all', [AIRequestLogController::class, 'clearAll'])->name('ai-logs.clear-all');
        Route::get('/{log}', [AIRequestLogController::class, 'show'])->name('ai-logs.show');
        Route::delete('/{log}', [AIRequestLogController::class, 'destroy'])->name('ai-logs.destroy');
    });
});
