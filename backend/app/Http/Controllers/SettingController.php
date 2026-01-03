<?php

namespace App\Http\Controllers;

use App\Models\AIPrompt;
use App\Models\AIProvider;
use App\Models\Setting;
use App\Services\AI\AIModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Show the settings page.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $user = $request->user();

        $settings = Setting::getMany([
            Setting::AI_PROVIDER,
            Setting::ANTHROPIC_API_KEY,
            Setting::OPENAI_API_KEY,
            Setting::GEMINI_API_KEY,
            Setting::PRICE_API_PROVIDER,
            Setting::SERPAPI_KEY,
            Setting::RAINFOREST_KEY,
            Setting::MAIL_DRIVER,
            Setting::MAIL_HOST,
            Setting::MAIL_PORT,
            Setting::MAIL_USERNAME,
            Setting::MAIL_PASSWORD,
            Setting::MAIL_FROM_ADDRESS,
            Setting::MAIL_FROM_NAME,
            Setting::MAIL_ENCRYPTION,
            Setting::HOME_ZIP_CODE,
            Setting::HOME_ADDRESS,
            Setting::HOME_LATITUDE,
            Setting::HOME_LONGITUDE,
            Setting::NOTIFY_PRICE_DROPS,
            Setting::NOTIFY_DAILY_SUMMARY,
            Setting::PRICE_CHECK_TIME,
            Setting::SUPPRESSED_VENDORS,
        ], $userId);

        // Get AI providers with dynamic model fetching
        $modelService = new AIModelService();
        
        $providers = $user->aiProviders()
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (AIProvider $provider) use ($modelService) {
                // Get dynamic models if provider has API key configured
                $availableModels = $provider->hasApiKey()
                    ? $modelService->getModelsForProvider($provider)
                    : $provider->getAvailableModels();
                    
                return [
                    'id' => $provider->id,
                    'provider' => $provider->provider,
                    'model' => $provider->model,
                    'base_url' => $provider->base_url,
                    'is_active' => $provider->is_active,
                    'is_primary' => $provider->is_primary,
                    'has_api_key' => $provider->hasApiKey(),
                    'masked_api_key' => $provider->getMaskedApiKey(),
                    'test_status' => $provider->test_status,
                    'test_error' => $provider->test_error,
                    'last_tested_at' => $provider->last_tested_at?->toISOString(),
                    'display_name' => $provider->getDisplayName(),
                    'available_models' => $availableModels,
                ];
            });

        // Get available provider types that haven't been added yet
        $existingProviders = $providers->pluck('provider')->toArray();
        $availableProviders = collect(AIProvider::$providers)
            ->filter(fn ($info, $key) => !in_array($key, $existingProviders))
            ->map(fn ($info, $key) => [
                'provider' => $key,
                'name' => $info['name'],
                'company' => $info['company'],
                'models' => $info['models'],
                'default_model' => $info['default_model'],
                'default_base_url' => $info['default_base_url'] ?? null,
                'requires_api_key' => $info['requires_api_key'],
            ])
            ->values();

        // Get AI prompts
        $prompts = AIPrompt::getAllForUser($userId);

        return Inertia::render('Settings', [
            'settings' => [
                'ai_provider' => $settings[Setting::AI_PROVIDER] ?? 'claude',
                'ai_api_key' => $settings[Setting::ANTHROPIC_API_KEY] || $settings[Setting::OPENAI_API_KEY] || $settings[Setting::GEMINI_API_KEY] ? '********' : null,
                'price_provider' => $settings[Setting::PRICE_API_PROVIDER] ?? 'serpapi',
                'price_api_key' => $settings[Setting::SERPAPI_KEY] || $settings[Setting::RAINFOREST_KEY] ? '********' : null,
                'has_price_api_key' => !empty($settings[Setting::SERPAPI_KEY]) || !empty($settings[Setting::RAINFOREST_KEY]),
                // Email settings
                'mail_driver' => $settings[Setting::MAIL_DRIVER] ?? 'smtp',
                'mail_host' => $settings[Setting::MAIL_HOST] ?? '',
                'mail_port' => $settings[Setting::MAIL_PORT] ?? '587',
                'mail_username' => $settings[Setting::MAIL_USERNAME] ?? '',
                'mail_password' => $settings[Setting::MAIL_PASSWORD] ? '********' : '',
                'mail_from_address' => $settings[Setting::MAIL_FROM_ADDRESS] ?? '',
                'mail_from_name' => $settings[Setting::MAIL_FROM_NAME] ?? '',
                'mail_encryption' => $settings[Setting::MAIL_ENCRYPTION] ?? 'tls',
                // Location
                'home_zip_code' => $settings[Setting::HOME_ZIP_CODE] ?? '',
                'home_address' => $settings[Setting::HOME_ADDRESS] ?? '',
                'home_latitude' => $settings[Setting::HOME_LATITUDE] ? (float) $settings[Setting::HOME_LATITUDE] : null,
                'home_longitude' => $settings[Setting::HOME_LONGITUDE] ? (float) $settings[Setting::HOME_LONGITUDE] : null,
                // Notification preferences
                'notification_email' => $settings[Setting::MAIL_FROM_ADDRESS],
                'notify_price_drops' => (bool) ($settings[Setting::NOTIFY_PRICE_DROPS] ?? true),
                'notify_daily_summary' => (bool) ($settings[Setting::NOTIFY_DAILY_SUMMARY] ?? false),
                'notify_all_time_lows' => true, // Default for now
                // Price check schedule
                'price_check_time' => $settings[Setting::PRICE_CHECK_TIME] ?? '03:00',
                // Vendor settings
                'suppressed_vendors' => json_decode($settings[Setting::SUPPRESSED_VENDORS] ?? '[]', true) ?: [],
            ],
            // AI Provider data
            'providers' => $providers,
            'availableProviders' => $availableProviders,
            'providerInfo' => AIProvider::$providers,
            'prompts' => $prompts,
        ]);
    }

    /**
     * Update user settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['nullable', 'in:claude,openai,gemini,local'],
            'ai_api_key' => ['nullable', 'string'],
            'price_provider' => ['nullable', 'in:serpapi,rainforest'],
            'price_api_key' => ['nullable', 'string'],
            // Email settings
            'mail_driver' => ['nullable', 'in:smtp,sendmail,mailgun,ses,postmark'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'string', 'max:10'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            // Location
            'home_zip_code' => ['nullable', 'string', 'max:20'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'home_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'home_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            // Notification preferences
            'notification_email' => ['nullable', 'email'],
            'notify_price_drops' => ['nullable', 'boolean'],
            'notify_daily_summary' => ['nullable', 'boolean'],
            'notify_all_time_lows' => ['nullable', 'boolean'],
            // Price check schedule
            'price_check_time' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            // Vendor settings
            'suppressed_vendors' => ['nullable', 'array'],
            'suppressed_vendors.*' => ['string', 'max:255'],
        ]);

        $userId = $request->user()->id;

        // AI Provider (legacy - now handled by AIProviderController)
        if (isset($validated['ai_provider'])) {
            Setting::set(Setting::AI_PROVIDER, $validated['ai_provider'], $userId);
        }

        // AI API Key (only if not masked)
        if (isset($validated['ai_api_key']) && $validated['ai_api_key'] !== '********') {
            $aiKeyField = match($validated['ai_provider'] ?? 'claude') {
                'claude' => Setting::ANTHROPIC_API_KEY,
                'openai' => Setting::OPENAI_API_KEY,
                'gemini' => Setting::GEMINI_API_KEY,
                default => null,
            };
            if ($aiKeyField) {
                Setting::set($aiKeyField, $validated['ai_api_key'], $userId);
            }
        }

        // Price Provider
        if (isset($validated['price_provider'])) {
            Setting::set(Setting::PRICE_API_PROVIDER, $validated['price_provider'], $userId);
        }

        // Price API Key (only if not masked)
        if (isset($validated['price_api_key']) && $validated['price_api_key'] !== '********') {
            $priceKeyField = match($validated['price_provider'] ?? 'serpapi') {
                'serpapi' => Setting::SERPAPI_KEY,
                'rainforest' => Setting::RAINFOREST_KEY,
                default => null,
            };
            if ($priceKeyField) {
                Setting::set($priceKeyField, $validated['price_api_key'], $userId);
            }
        }

        // Email Settings
        if (isset($validated['mail_driver'])) {
            Setting::set(Setting::MAIL_DRIVER, $validated['mail_driver'], $userId);
        }
        if (isset($validated['mail_host'])) {
            Setting::set(Setting::MAIL_HOST, $validated['mail_host'], $userId);
        }
        if (isset($validated['mail_port'])) {
            Setting::set(Setting::MAIL_PORT, $validated['mail_port'], $userId);
        }
        if (isset($validated['mail_username'])) {
            Setting::set(Setting::MAIL_USERNAME, $validated['mail_username'], $userId);
        }
        if (isset($validated['mail_password']) && $validated['mail_password'] !== '********') {
            Setting::set(Setting::MAIL_PASSWORD, $validated['mail_password'], $userId);
        }
        if (isset($validated['mail_from_address'])) {
            Setting::set(Setting::MAIL_FROM_ADDRESS, $validated['mail_from_address'], $userId);
        }
        if (isset($validated['mail_from_name'])) {
            Setting::set(Setting::MAIL_FROM_NAME, $validated['mail_from_name'], $userId);
        }
        if (isset($validated['mail_encryption'])) {
            Setting::set(Setting::MAIL_ENCRYPTION, $validated['mail_encryption'], $userId);
        }

        // Location
        if (isset($validated['home_zip_code'])) {
            Setting::set(Setting::HOME_ZIP_CODE, $validated['home_zip_code'], $userId);
        }
        if (array_key_exists('home_address', $validated)) {
            Setting::set(Setting::HOME_ADDRESS, $validated['home_address'] ?? '', $userId);
        }
        if (array_key_exists('home_latitude', $validated)) {
            Setting::set(Setting::HOME_LATITUDE, $validated['home_latitude'] !== null ? (string) $validated['home_latitude'] : '', $userId);
        }
        if (array_key_exists('home_longitude', $validated)) {
            Setting::set(Setting::HOME_LONGITUDE, $validated['home_longitude'] !== null ? (string) $validated['home_longitude'] : '', $userId);
        }

        // Notification Email (legacy field - now we use mail_from_address)
        if (isset($validated['notification_email'])) {
            Setting::set(Setting::MAIL_FROM_ADDRESS, $validated['notification_email'], $userId);
        }

        // Notification Preferences
        if (isset($validated['notify_price_drops'])) {
            Setting::set(Setting::NOTIFY_PRICE_DROPS, $validated['notify_price_drops'] ? '1' : '0', $userId);
        }
        if (isset($validated['notify_daily_summary'])) {
            Setting::set(Setting::NOTIFY_DAILY_SUMMARY, $validated['notify_daily_summary'] ? '1' : '0', $userId);
        }

        // Price check schedule
        if (isset($validated['price_check_time'])) {
            Setting::set(Setting::PRICE_CHECK_TIME, $validated['price_check_time'], $userId);
        }

        // Vendor suppression list
        if (array_key_exists('suppressed_vendors', $validated)) {
            Setting::set(Setting::SUPPRESSED_VENDORS, json_encode($validated['suppressed_vendors'] ?? []), $userId);
        }

        return back()->with('success', 'Settings saved successfully!');
    }

    /**
     * Add a vendor to the suppressed vendors list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suppressVendor(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'vendor' => ['required', 'string', 'max:255'],
        ]);

        $userId = $request->user()->id;
        $vendorName = trim($validated['vendor']);

        // Get current suppressed vendors
        $suppressedJson = Setting::get(Setting::SUPPRESSED_VENDORS, $userId);
        $suppressed = $suppressedJson ? json_decode($suppressedJson, true) ?: [] : [];

        // Add vendor if not already in the list
        if (!in_array($vendorName, $suppressed)) {
            $suppressed[] = $vendorName;
            Setting::set(Setting::SUPPRESSED_VENDORS, json_encode($suppressed), $userId);
        }

        return response()->json([
            'success' => true,
            'message' => "'{$vendorName}' has been added to your suppressed vendors list.",
            'suppressed_vendors' => $suppressed,
        ]);
    }

    /**
     * Test email configuration by sending a test email.
     */
    public function testEmail(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        // Get user's email settings
        $settings = Setting::getMany([
            Setting::MAIL_DRIVER,
            Setting::MAIL_HOST,
            Setting::MAIL_PORT,
            Setting::MAIL_USERNAME,
            Setting::MAIL_PASSWORD,
            Setting::MAIL_FROM_ADDRESS,
            Setting::MAIL_FROM_NAME,
            Setting::MAIL_ENCRYPTION,
        ], $userId);

        // Validate required settings
        if (empty($settings[Setting::MAIL_HOST]) || empty($settings[Setting::MAIL_FROM_ADDRESS])) {
            return back()->with('error', 'Please configure your email settings first.');
        }

        try {
            // Temporarily override Laravel mail config
            Config::set('mail.default', $settings[Setting::MAIL_DRIVER] ?? 'smtp');
            Config::set('mail.mailers.smtp.host', $settings[Setting::MAIL_HOST]);
            Config::set('mail.mailers.smtp.port', (int) ($settings[Setting::MAIL_PORT] ?? 587));
            Config::set('mail.mailers.smtp.username', $settings[Setting::MAIL_USERNAME]);
            Config::set('mail.mailers.smtp.password', $settings[Setting::MAIL_PASSWORD]);
            Config::set('mail.mailers.smtp.encryption', $settings[Setting::MAIL_ENCRYPTION] === 'none' ? null : $settings[Setting::MAIL_ENCRYPTION]);
            Config::set('mail.from.address', $settings[Setting::MAIL_FROM_ADDRESS]);
            Config::set('mail.from.name', $settings[Setting::MAIL_FROM_NAME] ?? 'DanaVision');

            // Send test email
            Mail::raw('This is a test email from DanaVision to verify your email configuration is working correctly.', function ($message) use ($request, $settings) {
                $message->to($request->user()->email)
                    ->subject('DanaVision - Test Email')
                    ->from($settings[Setting::MAIL_FROM_ADDRESS], $settings[Setting::MAIL_FROM_NAME] ?? 'DanaVision');
            });

            return back()->with('success', 'Test email sent successfully! Check your inbox.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }
}
