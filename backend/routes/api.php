<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasskeyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\NotificationChannelConfigController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\MailSettingController;
use App\Http\Controllers\Api\NotificationSettingController;
use App\Http\Controllers\Api\DealScanController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\StorageSettingController;
use App\Http\Controllers\Api\FileManagerController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BackupSettingController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\NovuController;
use App\Http\Controllers\Api\NovuSettingController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Controllers\Api\LLMController;
use App\Http\Controllers\Api\LLMModelController;
use App\Http\Controllers\Api\ClientErrorController;
use App\Http\Controllers\Api\LLMSettingController;
use App\Http\Controllers\Api\AuthSettingController;
use App\Http\Controllers\Api\SSOSettingController;
use App\Http\Controllers\Api\UserSettingController;
use App\Http\Controllers\Api\UserNotificationSettingsController;
use App\Http\Controllers\Api\AccessLogController;
use App\Http\Controllers\Api\AppLogExportController;
use App\Http\Controllers\Api\LogRetentionController;
use App\Http\Controllers\Api\SuspiciousActivityController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\Admin\SearchAdminController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ChangelogController;
use App\Http\Controllers\Api\NotificationDeliveryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\GraphQLSettingController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\StripeSettingController;
use App\Http\Controllers\Api\StripePaymentController;
use App\Http\Controllers\Api\ShoppingListController;
use App\Http\Controllers\Api\ListItemController;
use App\Http\Controllers\Api\ListShareController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\SmartAddController;
use App\Http\Controllers\Api\PriceSearchSettingController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\AIJobController;
use App\Http\Controllers\Api\AIPromptController;
use App\Http\Controllers\Api\AskDanaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/version', [VersionController::class, 'index']);
Route::get('/health', fn() => response()->json(['status' => 'ok']));

// SSO Provider Info (public)
Route::get('/auth/sso/providers', [SSOController::class, 'providers']);

// Public system settings and branding (no auth required)
Route::get('/system-settings/public', [SystemSettingController::class, 'publicSettings']);
Route::get('/branding', [BrandingController::class, 'show']);

// Client error reporting (rate limited, no auth)
Route::post('/client-errors', [ClientErrorController::class, 'store'])
    ->middleware('throttle:10,1');

// Stripe webhook (public, no auth — signature verified in controller)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Email availability check for signup (rate limited to prevent enumeration)
    Route::post('/check-email', [AuthController::class, 'checkEmail'])
        ->middleware('throttle:10,1');

    // Registration & Login (rate limited)
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('rate.sensitive:register');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('rate.sensitive:login');
    
    // Password Reset (rate limited)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('rate.sensitive:password_reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('rate.sensitive:password_reset');
    
    // Email Verification
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])
        ->middleware('throttle:10,1');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])
        ->middleware('auth:sanctum');
    
    // SSO Routes
    // NOTE: The redirect (/sso/{provider}) and callback (/callback/{provider})
    // routes are in routes/web.php, NOT here. They need the 'web' middleware
    // for proper session/cookie handling during OAuth flows. See web.php comments.
    Route::post('/sso/{provider}/link', [SSOController::class, 'link'])
        ->middleware('auth:sanctum');
    Route::delete('/sso/{provider}/unlink', [SSOController::class, 'unlink'])
        ->middleware('auth:sanctum');
    Route::get('/sso/linked-accounts', [SSOController::class, 'linkedAccounts'])
        ->middleware('auth:sanctum');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->middleware('2fa.setup');
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // 2FA Routes
        Route::prefix('2fa')->group(function () {
            Route::get('/status', [TwoFactorController::class, 'status']);
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/disable', [TwoFactorController::class, 'disable']);
            // 2FA verify is rate limited to prevent brute force
            Route::post('/verify', [TwoFactorController::class, 'verify'])
                ->withoutMiddleware('auth:sanctum')
                ->middleware('rate.sensitive:2fa');
            Route::post('/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
            Route::post('/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
        });

        // Passkey Routes (authenticated management)
        Route::prefix('passkeys')->group(function () {
            Route::get('/', [PasskeyController::class, 'index']);
            Route::post('/register/options', [PasskeyController::class, 'registerOptions']);
            Route::post('/register', [PasskeyController::class, 'register']);
            Route::put('/{id}', [PasskeyController::class, 'update']);
            Route::delete('/{id}', [PasskeyController::class, 'destroy']);
        });
    });

    // Passkey login (unauthenticated, rate limited like other login methods)
    Route::post('/passkeys/login/options', [PasskeyController::class, 'loginOptions'])
        ->middleware('rate.sensitive:passkey');
    Route::post('/passkeys/login', [PasskeyController::class, 'login'])
        ->middleware('rate.sensitive:passkey');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'verified', '2fa.setup'])->group(function () {

    // Profile (access logging: User, self)
    Route::prefix('profile')->middleware('log.access:User')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
        Route::delete('/', [ProfileController::class, 'destroy']);
    });

    // Novu subscriber HMAC (for Inbox auth)
    Route::get('/novu/subscriber-hash', [NovuController::class, 'subscriberHash']);

    // User Settings (access logging: Setting, self)
    Route::prefix('user')->group(function () {
        Route::get('/settings', [UserSettingController::class, 'show'])->middleware('log.access:Setting');
        Route::put('/settings', [UserSettingController::class, 'update'])->middleware('log.access:Setting');
        Route::post('/settings/detect-timezone', [UserSettingController::class, 'detectTimezone']);
        Route::get('/notification-settings', [UserNotificationSettingsController::class, 'show'])->middleware('log.access:Setting');
        Route::put('/notification-settings', [UserNotificationSettingsController::class, 'update'])->middleware('log.access:Setting');
        Route::get('/notification-settings/type-preferences', [UserNotificationSettingsController::class, 'typePreferences'])->middleware('log.access:Setting');
        Route::put('/notification-settings/type-preferences', [UserNotificationSettingsController::class, 'updateTypePreference'])->middleware('log.access:Setting');
        Route::get('/webpush-subscriptions', [UserNotificationSettingsController::class, 'listWebPushSubscriptions'])->middleware('log.access:Setting');
        Route::post('/webpush-subscription', [UserNotificationSettingsController::class, 'storeWebPushSubscription'])->middleware('log.access:Setting');
        Route::delete('/webpush-subscription', [UserNotificationSettingsController::class, 'destroyWebPushSubscription'])->middleware('log.access:Setting');
        Route::delete('/webpush-subscription/{id}', [UserNotificationSettingsController::class, 'destroyWebPushSubscriptionById'])->whereNumber('id')->middleware('log.access:Setting');
    });

    // Dashboard (widget data)
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/environment', [DashboardController::class, 'environment'])->middleware('can:settings.view');

    // Onboarding (wizard status and progress)
    Route::prefix('onboarding')->group(function () {
        Route::get('/status', [OnboardingController::class, 'status']);
        Route::post('/wizard/complete', [OnboardingController::class, 'completeWizard']);
        Route::post('/wizard/dismiss', [OnboardingController::class, 'dismissWizard']);
        Route::post('/wizard/step', [OnboardingController::class, 'completeStep']);
        Route::post('/wizard/reset', [OnboardingController::class, 'resetWizard']);
    });
    
    // Changelog (authenticated, read-only)
    Route::get('/changelog', [ChangelogController::class, 'index']);
    Route::get('/changelog/versions', [ChangelogController::class, 'versions']);
    Route::get('/changelog/export', [ChangelogController::class, 'export'])->middleware('throttle:10,1');

    // Settings (permission: settings.view / settings.edit)
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->middleware('can:settings.view');
        Route::put('/', [SettingController::class, 'update'])->middleware('can:settings.edit');
        // Legacy routes - deprecated, use /user/notification-settings instead
        Route::get('/notifications', [UserNotificationSettingsController::class, 'show'])->middleware(['log.access:Setting', 'deprecate:/api/user/notification-settings']);
        Route::put('/notifications', [UserNotificationSettingsController::class, 'update'])->middleware(['log.access:Setting', 'deprecate:/api/user/notification-settings']);
        Route::get('/{group}', [SettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/{group}', [SettingController::class, 'updateGroup'])->middleware('can:settings.edit');
    });

    // System Settings (permission: settings.view / settings.edit)
    Route::prefix('system-settings')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->middleware('can:settings.view');
        Route::put('/', [SystemSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::get('/{group}', [SystemSettingController::class, 'show'])->middleware('can:settings.view');
    });

    // Admin notification channel config (permission: settings.view / settings.edit)
    Route::prefix('admin')->group(function () {
        Route::get('notification-channels', [NotificationChannelConfigController::class, 'index'])->middleware('can:settings.view');
        Route::put('notification-channels', [NotificationChannelConfigController::class, 'update'])->middleware('can:settings.edit');
        Route::post('notification-channels/test-all', [NotificationChannelConfigController::class, 'testAll'])->middleware('can:settings.edit');
        Route::get('notification-channels/verify', [NotificationChannelConfigController::class, 'verify'])->middleware('can:settings.view');
    });
    
    // Search (authenticated; returns user data when searching users — access logged)
    Route::get('/search', [SearchController::class, 'search'])->middleware('log.access:User');
    Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->middleware('log.access:User');

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        Route::post('/delete-batch', [NotificationController::class, 'destroyBatch']);

        // Test notification (throttled: 5 per minute)
        Route::post('/test/{channel}', [NotificationController::class, 'test'])->middleware('throttle:5,1');

        // Push notification diagnostic
        Route::get('/diagnose-push', [NotificationController::class, 'diagnosePush']);
    });
    
    // LLM/AI (permission: settings.view / settings.edit)
    Route::prefix('llm')->group(function () {
        Route::get('/providers', [LLMController::class, 'providers'])->middleware('can:settings.view');
        Route::post('/providers', [LLMController::class, 'storeProvider'])->middleware('can:settings.edit');
        Route::put('/providers/{provider}', [LLMController::class, 'updateProvider'])->middleware('can:settings.edit');
        Route::delete('/providers/{provider}', [LLMController::class, 'destroyProvider'])->middleware('can:settings.edit');
        Route::get('/config', [LLMController::class, 'config'])->middleware('can:settings.view');
        Route::put('/config', [LLMController::class, 'updateConfig'])->middleware('can:settings.edit');
        Route::post('/test/{provider}', [LLMController::class, 'testProvider'])->middleware('can:settings.edit');
        Route::post('/query', [LLMController::class, 'query'])->middleware('can:settings.view');
        Route::post('/query/vision', [LLMController::class, 'visionQuery'])->middleware('can:settings.view');
    });

    // Backup & Restore (permission: backups.view / backups.create / backups.restore / backups.delete)
    Route::prefix('backup')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->middleware('can:backups.view');
        Route::post('/create', [BackupController::class, 'create'])->middleware('can:backups.create');
        Route::post('/upload', [BackupController::class, 'upload'])->middleware('can:backups.create');
        Route::get('/download/{filename}', [BackupController::class, 'download'])->middleware('can:backups.view');
        Route::post('/restore', [BackupController::class, 'restore'])->middleware('can:backups.restore');
        Route::delete('/{filename}', [BackupController::class, 'destroy'])->middleware('can:backups.delete');
    });

    // User Management (permission: users.view / users.create / users.edit / users.delete, access logging: User)
    Route::prefix('users')->middleware('log.access:User')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('can:users.view');
        Route::post('/', [UserController::class, 'store'])->middleware('can:users.create');
        Route::get('/{user}', [UserController::class, 'show'])->middleware('can:users.view');
        Route::put('/{user}', [UserController::class, 'update'])->middleware('can:users.edit');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');
        Route::post('/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->middleware('can:users.edit');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('can:users.edit');
        Route::post('/{user}/disable', [UserController::class, 'toggleDisabled'])->middleware('can:users.edit');
        Route::post('/{user}/resend-verification', [UserController::class, 'resendVerification'])->middleware('can:users.edit');
        Route::put('/{user}/groups', [UserController::class, 'updateGroups'])->middleware('can:users.edit');
    });

    // Permissions list (permission: groups.view, for UI dropdowns)
    Route::get('/permissions', [GroupController::class, 'availablePermissions'])->middleware('can:groups.view');

    // User Groups (permission: groups.view / groups.manage)
    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->middleware('can:groups.view');
        Route::post('/', [GroupController::class, 'store'])->middleware('can:groups.manage');
        Route::get('/{group}', [GroupController::class, 'show'])->middleware('can:groups.view');
        Route::put('/{group}', [GroupController::class, 'update'])->middleware('can:groups.manage');
        Route::delete('/{group}', [GroupController::class, 'destroy'])->middleware('can:groups.manage');
        Route::get('/{group}/members', [GroupController::class, 'members'])->middleware('can:groups.view');
        Route::post('/{group}/members', [GroupController::class, 'addMembers'])->middleware('can:groups.manage');
        Route::delete('/{group}/members/{user}', [GroupController::class, 'removeMember'])->middleware('can:groups.manage');
        Route::get('/{group}/permissions', [GroupController::class, 'permissions'])->middleware('can:groups.view');
        Route::put('/{group}/permissions', [GroupController::class, 'updatePermissions'])->middleware('can:groups.manage');
    });

    // Audit Logs (permission: audit.view / logs.export)
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->middleware('can:audit.view');
        Route::get('/export', [AuditLogController::class, 'export'])->middleware('can:logs.export');
        Route::get('/stats', [AuditLogController::class, 'stats'])->middleware('can:audit.view');
    });

    // Access Logs / HIPAA (permission: logs.view / logs.export)
    Route::prefix('access-logs')->group(function () {
        Route::get('/', [AccessLogController::class, 'index'])->middleware('can:logs.view');
        Route::get('/export', [AccessLogController::class, 'export'])->middleware('can:logs.export');
        Route::get('/stats', [AccessLogController::class, 'stats'])->middleware('can:logs.view');
        Route::delete('/', [AccessLogController::class, 'deleteAll'])->middleware('can:logs.delete');
    });

    // Application logs export (permission: logs.export)
    Route::prefix('app-logs')->group(function () {
        Route::get('/recent', [AppLogExportController::class, 'recent'])->middleware('can:logs.export');
        Route::get('/export', [AppLogExportController::class, 'export'])->middleware('can:logs.export');
    });

    // Log retention and cleanup config (permission: settings.edit)
    Route::prefix('log-retention')->group(function () {
        Route::get('/', [LogRetentionController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [LogRetentionController::class, 'update'])->middleware('can:settings.edit');
    });

    // Integration Usage (permission: usage.view / logs.export)
    Route::prefix('usage')->group(function () {
        Route::get('/stats', [UsageController::class, 'stats'])->middleware('can:usage.view');
        Route::get('/breakdown', [UsageController::class, 'breakdown'])->middleware('can:usage.view');
        Route::get('/export', [UsageController::class, 'export'])->middleware('can:logs.export');
    });

    // Suspicious activity alerts (permission: logs.view)
    Route::get('/suspicious-activity', [SuspiciousActivityController::class, 'index'])->middleware('can:logs.view');
    
    // Email Templates (permission: settings.view / settings.edit)
    Route::prefix('email-templates')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index'])->middleware('can:settings.view');
        Route::get('/{key}', [EmailTemplateController::class, 'show'])->middleware('can:settings.view');
        Route::put('/{key}', [EmailTemplateController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/{key}/preview', [EmailTemplateController::class, 'preview'])->middleware('can:settings.view');
        Route::post('/{key}/test', [EmailTemplateController::class, 'test'])->middleware('can:settings.edit');
        Route::post('/{key}/reset', [EmailTemplateController::class, 'reset'])->middleware('can:settings.edit');
    });

    // Notification Templates (permission: settings.view / settings.edit)
    Route::prefix('notification-templates')->group(function () {
        Route::get('/', [NotificationTemplateController::class, 'index'])->middleware('can:settings.view');
        Route::get('/{id}', [NotificationTemplateController::class, 'show'])->middleware('can:settings.view');
        Route::put('/{id}', [NotificationTemplateController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/{id}/preview', [NotificationTemplateController::class, 'preview'])->middleware('can:settings.view');
        Route::post('/{id}/reset', [NotificationTemplateController::class, 'reset'])->middleware('can:settings.edit');
    });

    // Mail Settings (permission: settings.view / settings.edit)
    Route::prefix('mail-settings')->group(function () {
        Route::get('/', [MailSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [MailSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/test', [MailSettingController::class, 'sendTestEmail'])->middleware('can:settings.edit');
        Route::delete('/keys/{key}', [MailSettingController::class, 'reset'])->middleware('can:settings.edit');
    });

    // Notification Channel Settings (permission: settings.view / settings.edit)
    Route::prefix('notification-settings')->group(function () {
        Route::get('/', [NotificationSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [NotificationSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/generate-vapid', [NotificationSettingController::class, 'generateVapid'])->middleware('can:settings.edit');
        Route::post('/test/{channel}', [NotificationSettingController::class, 'testChannel'])->middleware('can:settings.edit');
        Route::delete('/keys/{key}', [NotificationSettingController::class, 'reset'])->middleware('can:settings.edit');
    });

    // Notification Delivery Log (permission: notification_deliveries.view)
    Route::prefix('notification-deliveries')->group(function () {
        Route::get('/', [NotificationDeliveryController::class, 'index'])->middleware('can:notification_deliveries.view');
        Route::get('/stats', [NotificationDeliveryController::class, 'stats'])->middleware('can:notification_deliveries.view');
    });

    // Novu Settings (permission: settings.view / settings.edit)
    Route::prefix('novu-settings')->group(function () {
        Route::get('/', [NovuSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [NovuSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/test', [NovuSettingController::class, 'test'])->middleware('can:settings.edit');
        Route::get('/workflow-map', [NovuSettingController::class, 'workflowMap'])->middleware('can:settings.view');
        Route::put('/workflow-map', [NovuSettingController::class, 'updateWorkflowMap'])->middleware('can:settings.edit');
        Route::delete('/keys/{key}', [NovuSettingController::class, 'resetKey'])->middleware('can:settings.edit');
    });

    // LLM System-Wide Settings (permission: settings.view / settings.edit)
    Route::prefix('llm-settings')->group(function () {
        Route::get('/', [LLMSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [LLMSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::delete('/keys/{key}', [LLMSettingController::class, 'reset'])->middleware('can:settings.edit');
        Route::post('/test-key', [LLMModelController::class, 'testKey'])->middleware('can:settings.edit');
        Route::post('/discover-models', [LLMModelController::class, 'discover'])->middleware('can:settings.edit');
    });

    // Auth Settings (permission: settings.view / settings.edit)
    Route::prefix('auth-settings')->group(function () {
        Route::get('/', [AuthSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [AuthSettingController::class, 'update'])->middleware('can:settings.edit');
    });

    // SSO Settings (permission: settings.view / settings.edit)
    Route::prefix('sso-settings')->group(function () {
        Route::get('/', [SSOSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [SSOSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/test/{provider}', [SSOSettingController::class, 'test'])->middleware('can:settings.edit');
        Route::delete('/keys/{key}', [SSOSettingController::class, 'reset'])->middleware('can:settings.edit');
    });

    // Stripe Settings (permission: settings.view / settings.edit)
    Route::prefix('stripe/settings')->group(function () {
        Route::get('/', [StripeSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [StripeSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/test', [StripeSettingController::class, 'testConnection'])->middleware('can:settings.edit');
        Route::delete('/keys/{key}', [StripeSettingController::class, 'reset'])->middleware('can:settings.edit');
    });

    // Payments (permission: payments.view / payments.manage)
    Route::prefix('payments')->group(function () {
        Route::get('/', [StripePaymentController::class, 'index'])->middleware('can:payments.view');
        Route::post('/intent', [StripePaymentController::class, 'createIntent'])->middleware('can:payments.manage');
        Route::get('/admin', [StripePaymentController::class, 'adminIndex'])->middleware('can:payments.manage');
        Route::get('/{payment}', [StripePaymentController::class, 'show'])->middleware('can:payments.view');
    });

    // Backup Settings (permission: settings.view / settings.edit)
    Route::prefix('backup-settings')->group(function () {
        Route::get('/', [BackupSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [BackupSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/reset/{key}', [BackupSettingController::class, 'reset'])->middleware('can:settings.edit');
        Route::post('/test/{destination}', [BackupSettingController::class, 'testDestination'])->middleware('can:settings.edit');
    });

    // Search Admin (permission: settings.view / settings.edit for reindex)
    Route::prefix('admin')->group(function () {
        Route::get('/search/stats', [SearchAdminController::class, 'stats'])->middleware('can:settings.view');
        Route::get('/search/health', [SearchAdminController::class, 'health'])->middleware('can:settings.view');
        Route::post('/search/test-connection', [SearchAdminController::class, 'testConnection'])->middleware('can:settings.edit');
        Route::post('/search/reindex', [SearchAdminController::class, 'reindex'])->middleware('can:settings.edit');
    });

    // Jobs (permission: settings.view / settings.edit for run)
    Route::prefix('jobs')->group(function () {
        Route::get('/scheduled', [JobController::class, 'scheduled'])->middleware('can:settings.view');
        Route::post('/run/{command}', [JobController::class, 'run'])->middleware('can:settings.edit');
        Route::get('/queue', [JobController::class, 'queueStatus'])->middleware('can:settings.view');
        Route::get('/failed', [JobController::class, 'failedJobs'])->middleware('can:settings.view');
        // Static routes must come before parameterized routes to avoid shadowing
        Route::post('/failed/retry-all', [JobController::class, 'retryAllFailed'])->middleware('can:settings.edit');
        Route::delete('/failed/clear', [JobController::class, 'clearFailed'])->middleware('can:settings.edit');
        Route::post('/failed/{id}/retry', [JobController::class, 'retryJob'])->middleware('can:settings.edit');
        Route::delete('/failed/{id}', [JobController::class, 'deleteJob'])->middleware('can:settings.edit');
    });

    // Storage Settings (permission: settings.view / settings.edit)
    Route::prefix('storage-settings')->group(function () {
        Route::get('/', [StorageSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [StorageSettingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/test', [StorageSettingController::class, 'test'])->middleware('can:settings.edit');
        Route::get('/stats', [StorageSettingController::class, 'stats'])->middleware('can:settings.view');
        Route::get('/analytics', [StorageSettingController::class, 'analytics'])->middleware('can:settings.view');
        Route::get('/cleanup-suggestions', [StorageSettingController::class, 'cleanupSuggestions'])->middleware('can:settings.view');
        Route::post('/cleanup', [StorageSettingController::class, 'cleanup'])->middleware('can:settings.edit');
        Route::get('/paths', [StorageSettingController::class, 'paths'])->middleware('can:settings.view');
        Route::get('/health', [StorageSettingController::class, 'health'])->middleware('can:settings.view');
    });

    // File Manager (admin only)
    Route::prefix('storage/files')->middleware('can:admin')->group(function () {
        Route::get('/', [FileManagerController::class, 'index']);
        Route::post('/', [FileManagerController::class, 'upload']);
        Route::get('/{path}/download', [FileManagerController::class, 'download'])->where('path', '.*');
        Route::put('/{path}/rename', [FileManagerController::class, 'rename'])->where('path', '.*');
        Route::put('/{path}/move', [FileManagerController::class, 'move'])->where('path', '.*');
        Route::get('/{path}', [FileManagerController::class, 'show'])->where('path', '.*');
        Route::delete('/{path}', [FileManagerController::class, 'destroy'])->where('path', '.*');
    });
    
    // API Tokens — deprecated, use /user/api-keys instead (ApiKeyController)
    Route::prefix('api-tokens')->middleware('deprecate:/api/user/api-keys')->group(function () {
        Route::get('/', [ApiTokenController::class, 'index']);
        Route::post('/', [ApiTokenController::class, 'store']);
        Route::delete('/{token}', [ApiTokenController::class, 'destroy']);
    });

    // GraphQL API Keys (Authenticated users — sk_ prefixed keys)
    Route::prefix('user/api-keys')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index']);
        Route::post('/', [ApiKeyController::class, 'store']);
        Route::put('/{id}', [ApiKeyController::class, 'update']);
        Route::delete('/{id}', [ApiKeyController::class, 'destroy']);
        Route::post('/{id}/rotate', [ApiKeyController::class, 'rotate']);
    });

    // GraphQL Settings (admin)
    Route::prefix('graphql/settings')->group(function () {
        Route::get('/', [GraphQLSettingController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [GraphQLSettingController::class, 'update'])->middleware('can:settings.edit');
    });

    Route::prefix('graphql/admin')->group(function () {
        Route::get('/api-keys', [GraphQLSettingController::class, 'adminApiKeys'])->middleware('can:api_keys.manage');
        Route::get('/api-keys/stats', [GraphQLSettingController::class, 'adminApiKeyStats'])->middleware('can:api_keys.manage');
        Route::delete('/api-keys/{id}', [GraphQLSettingController::class, 'adminRevokeKey'])->middleware('can:api_keys.manage');
        Route::get('/usage-stats', [GraphQLSettingController::class, 'usageStats'])->middleware('can:settings.view');
    });

    // Webhooks (permission: settings.edit)
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'index'])->middleware('can:settings.view');
        Route::post('/', [WebhookController::class, 'store'])->middleware('can:settings.edit');
        // More specific routes must come before parameterized routes
        Route::get('/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->middleware('can:settings.view');
        Route::post('/{webhook}/test', [WebhookController::class, 'test'])->middleware('can:settings.edit');
        Route::get('/{webhook}', [WebhookController::class, 'show'])->middleware('can:settings.view');
        Route::put('/{webhook}', [WebhookController::class, 'update'])->middleware('can:settings.edit');
        Route::delete('/{webhook}', [WebhookController::class, 'destroy'])->middleware('can:settings.edit');
    });

    // Branding (permission: settings.edit - public read is defined above)
    Route::prefix('branding')->group(function () {
        Route::put('/', [BrandingController::class, 'update'])->middleware('can:settings.edit');
        Route::post('/logo', [BrandingController::class, 'uploadLogo'])->middleware('can:settings.edit');
        Route::post('/logo-dark', [BrandingController::class, 'uploadLogoDark'])->middleware('can:settings.edit');
        Route::post('/favicon', [BrandingController::class, 'uploadFavicon'])->middleware('can:settings.edit');
        Route::delete('/logo', [BrandingController::class, 'deleteLogo'])->middleware('can:settings.edit');
        Route::delete('/logo-dark', [BrandingController::class, 'deleteLogoDark'])->middleware('can:settings.edit');
        Route::delete('/favicon', [BrandingController::class, 'deleteFavicon'])->middleware('can:settings.edit');
    });

    // Shopping Lists
    Route::apiResource('lists', ShoppingListController::class);
    Route::post('/lists/{list}/refresh', [ShoppingListController::class, 'refresh']);
    Route::post('/lists/{list}/analyze', [ShoppingListController::class, 'analyze']);
    Route::get('/lists/{list}/analysis', [ShoppingListController::class, 'analysis']);

    // List Items
    Route::get('/items', [ListItemController::class, 'index']);
    Route::get('/items/{item}', [ListItemController::class, 'show']);
    Route::post('/lists/{list}/items', [ListItemController::class, 'store']);
    Route::put('/items/{item}', [ListItemController::class, 'update']);
    Route::delete('/items/{item}', [ListItemController::class, 'destroy']);
    Route::post('/items/{item}/refresh', [ListItemController::class, 'refresh']);
    Route::post('/items/{item}/purchased', [ListItemController::class, 'markPurchased']);
    Route::get('/items/{item}/history', [ListItemController::class, 'history']);
    Route::post('/items/{item}/smart-fill', [ListItemController::class, 'smartFill']);

    // Sharing
    Route::get('/lists/{list}/shares', [ListShareController::class, 'index']);
    Route::post('/lists/{list}/shares', [ListShareController::class, 'store']);
    Route::put('/shares/{share}', [ListShareController::class, 'update']);
    Route::delete('/shares/{share}', [ListShareController::class, 'destroy']);
    Route::get('/shares/pending', [ListShareController::class, 'pending']);
    Route::post('/shares/{share}/accept', [ListShareController::class, 'accept']);
    Route::post('/shares/{share}/decline', [ListShareController::class, 'decline']);

    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/preferences', [StoreController::class, 'userPreferences']);
    Route::put('/stores/preferences', [StoreController::class, 'updatePreferences']);
    Route::patch('/stores/priorities', [StoreController::class, 'updatePriorities']);
    Route::get('/stores/suppressed', [StoreController::class, 'suppressedVendors']);
    Route::get('/stores/nearby/availability', [StoreController::class, 'nearbyAvailability']);
    Route::post('/stores/nearby/preview', [StoreController::class, 'nearbyPreview']);
    Route::post('/stores/nearby/add', [StoreController::class, 'nearbyAdd']);
    Route::get('/stores/address-search', [StoreController::class, 'searchAddress']);
    Route::get('/stores/address-geocode', [StoreController::class, 'geocodePlace']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);
    Route::put('/stores/{store}', [StoreController::class, 'update']);
    Route::delete('/stores/{store}', [StoreController::class, 'destroy']);
    Route::post('/stores/{store}/suppress', [StoreController::class, 'suppress']);
    Route::post('/stores/{store}/restore', [StoreController::class, 'restore']);
    Route::post('/stores/{store}/favorite', [StoreController::class, 'toggleFavorite']);
    Route::post('/stores/{store}/parent', [StoreController::class, 'linkParent']);
    Route::delete('/stores/{store}/parent', [StoreController::class, 'unlinkParent']);

    // Smart Add
    Route::post('/smart-add/upload', [SmartAddController::class, 'upload']);
    Route::get('/smart-add/queue', [SmartAddController::class, 'queue']);
    Route::post('/smart-add/{item}/accept', [SmartAddController::class, 'acceptItem']);
    Route::delete('/smart-add/{item}', [SmartAddController::class, 'rejectItem']);

    // Deals & Coupons
    Route::post('/deals/scan', [DealScanController::class, 'scan']);
    Route::get('/deals/queue', [DealScanController::class, 'queue']);
    Route::post('/deals/scans/{scan}/accept-all', [DealScanController::class, 'acceptAll']);
    Route::get('/deals/savings/{list}', [DealScanController::class, 'listSavings']);
    Route::get('/deals', [DealScanController::class, 'index']);
    Route::post('/deals', [DealScanController::class, 'store']);
    Route::get('/deals/{deal}', [DealScanController::class, 'show']);
    Route::put('/deals/{deal}', [DealScanController::class, 'update']);
    Route::post('/deals/{deal}/accept', [DealScanController::class, 'acceptDeal']);
    Route::delete('/deals/{deal}', [DealScanController::class, 'dismissDeal']);
    Route::post('/deals/{deal}/match/{item}', [DealScanController::class, 'matchToItem']);
    Route::delete('/deals/{deal}/match', [DealScanController::class, 'unmatch']);

    // Product Search
    Route::post('/product-search', [ProductSearchController::class, 'search']);
    Route::post('/product-search/image', [ProductSearchController::class, 'imageSearch']);
    Route::get('/search-history', [ProductSearchController::class, 'history']);

    // Price Search Settings (permission: settings.edit for test)
    Route::post('/price-search-settings/test/{provider}', [PriceSearchSettingController::class, 'testProvider'])->middleware('can:settings.edit');

    // AI Jobs
    Route::get('/ai-jobs', [AIJobController::class, 'index']);
    Route::get('/ai-jobs/{job}', [AIJobController::class, 'show']);
    Route::post('/ai-jobs/{job}/cancel', [AIJobController::class, 'cancel']);

    // AI Prompts (user custom prompts)
    Route::get('/ai-prompts', [AIPromptController::class, 'index']);
    Route::post('/ai-prompts', [AIPromptController::class, 'store']);
    Route::get('/ai-prompts/active', [AIPromptController::class, 'active']);
    Route::put('/ai-prompts/{prompt}', [AIPromptController::class, 'update']);
    Route::delete('/ai-prompts/{prompt}', [AIPromptController::class, 'destroy']);

    // Dashboard Shopping Stats
    Route::get('/dashboard/shopping-stats', [DashboardController::class, 'shoppingStats']);

    // Ask Dana (AI Assistant)
    Route::prefix('ask-dana')->group(function () {
        Route::get('/conversations', [AskDanaController::class, 'index']);
        Route::post('/conversations', [AskDanaController::class, 'store']);
        Route::get('/conversations/{conversation}', [AskDanaController::class, 'show']);
        Route::patch('/conversations/{conversation}', [AskDanaController::class, 'update']);
        Route::delete('/conversations/{conversation}', [AskDanaController::class, 'destroy']);
        Route::post('/conversations/{conversation}/messages', [AskDanaController::class, 'sendMessage']);
    });

});
