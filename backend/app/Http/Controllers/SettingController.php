<?php

namespace App\Http\Controllers;

use App\Models\AIPrompt;
use App\Models\AIProvider;
use App\Models\Setting;
use App\Models\Store;
use App\Models\UserStorePreference;
use App\Services\AI\AIModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
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
            Setting::FIRECRAWL_API_KEY,
            Setting::GOOGLE_PLACES_API_KEY,
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

        // Get stores with user preferences
        $stores = $this->getStoresWithPreferences($userId);

        return Inertia::render('Settings', [
            'settings' => [
                'ai_provider' => $settings[Setting::AI_PROVIDER] ?? 'claude',
                'ai_api_key' => $settings[Setting::ANTHROPIC_API_KEY] || $settings[Setting::OPENAI_API_KEY] || $settings[Setting::GEMINI_API_KEY] ? '********' : null,
                // Firecrawl Web Crawler (primary price search provider)
                'firecrawl_api_key' => $settings[Setting::FIRECRAWL_API_KEY] ? '********' : null,
                'has_firecrawl_api_key' => !empty($settings[Setting::FIRECRAWL_API_KEY]),
                // Google Places API (for nearby store discovery)
                'google_places_api_key' => $settings[Setting::GOOGLE_PLACES_API_KEY] ? '********' : null,
                'has_google_places_api_key' => !empty($settings[Setting::GOOGLE_PLACES_API_KEY]),
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
            // Store Registry data
            'stores' => $stores,
            'storeCategories' => [
                Store::CATEGORY_GENERAL => 'General Retailers',
                Store::CATEGORY_ELECTRONICS => 'Electronics',
                Store::CATEGORY_GROCERY => 'Grocery',
                Store::CATEGORY_HOME => 'Home & Garden',
                Store::CATEGORY_CLOTHING => 'Clothing',
                Store::CATEGORY_PHARMACY => 'Pharmacy',
                Store::CATEGORY_WAREHOUSE => 'Warehouse Clubs',
                Store::CATEGORY_PET => 'Pet Stores',
                Store::CATEGORY_SPECIALTY => 'Specialty',
            ],
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
            // Firecrawl Web Crawler (primary price search provider)
            'firecrawl_api_key' => ['nullable', 'string'],
            // Google Places API (for nearby store discovery)
            'google_places_api_key' => ['nullable', 'string'],
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

        // Firecrawl API Key (only if not masked)
        if (isset($validated['firecrawl_api_key']) && $validated['firecrawl_api_key'] !== '********') {
            Setting::set(Setting::FIRECRAWL_API_KEY, $validated['firecrawl_api_key'], $userId);
        }

        // Google Places API Key (only if not masked)
        if (isset($validated['google_places_api_key']) && $validated['google_places_api_key'] !== '********') {
            Setting::set(Setting::GOOGLE_PLACES_API_KEY, $validated['google_places_api_key'], $userId);
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

    /**
     * Get stores with user preferences for the settings page.
     *
     * @param int $userId
     * @return array
     */
    protected function getStoresWithPreferences(int $userId): array
    {
        // Get all active stores
        $stores = Store::active()
            ->orderByDesc('default_priority')
            ->get();

        // Get user preferences
        $preferences = UserStorePreference::where('user_id', $userId)
            ->get()
            ->keyBy('store_id');

        return $stores->map(function (Store $store) use ($preferences) {
            $preference = $preferences->get($store->id);
            
            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'domain' => $store->domain,
                'logo_url' => $store->logo_url,
                'category' => $store->category,
                'is_default' => $store->is_default,
                'is_local' => $store->is_local,
                'has_search_template' => !empty($store->search_url_template),
                'auto_configured' => $store->auto_configured ?? false,
                'address' => $store->address,
                'phone' => $store->phone,
                'default_priority' => $store->default_priority,
                // User preferences (or defaults)
                'enabled' => $preference ? $preference->enabled : true,
                'is_favorite' => $preference ? $preference->is_favorite : false,
                'priority' => $preference ? $preference->priority : $store->default_priority,
            ];
        })->values()->toArray();
    }

    /**
     * Get all stores with user preferences (API endpoint).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStores(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = $request->user()->id;
        $stores = $this->getStoresWithPreferences($userId);

        return response()->json([
            'success' => true,
            'stores' => $stores,
        ]);
    }

    /**
     * Update a store preference for the current user.
     *
     * @param Request $request
     * @param int $storeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStorePreference(Request $request, int $storeId): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $userId = $request->user()->id;

        // Verify store exists
        $store = Store::find($storeId);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        // Update or create preference
        $preference = UserStorePreference::setPreference($userId, $storeId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Store preference updated',
            'preference' => [
                'store_id' => $preference->store_id,
                'enabled' => $preference->enabled,
                'is_favorite' => $preference->is_favorite,
                'priority' => $preference->priority,
            ],
        ]);
    }

    /**
     * Toggle favorite status for a store.
     *
     * @param Request $request
     * @param int $storeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStoreFavorite(Request $request, int $storeId): \Illuminate\Http\JsonResponse
    {
        $userId = $request->user()->id;

        // Verify store exists
        $store = Store::find($storeId);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        $preference = UserStorePreference::toggleFavorite($userId, $storeId);

        return response()->json([
            'success' => true,
            'message' => $preference->is_favorite ? 'Store added to favorites' : 'Store removed from favorites',
            'is_favorite' => $preference->is_favorite,
        ]);
    }

    /**
     * Toggle local status for a store.
     *
     * @param Request $request
     * @param int $storeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStoreLocal(Request $request, int $storeId): \Illuminate\Http\JsonResponse
    {
        $store = Store::find($storeId);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        $store->is_local = !$store->is_local;
        $store->save();

        return response()->json([
            'success' => true,
            'message' => $store->is_local ? 'Store marked as local' : 'Store unmarked as local',
            'is_local' => $store->is_local,
        ]);
    }

    /**
     * Bulk update store priorities (for drag-and-drop reordering).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStorePriorities(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'store_order' => ['required', 'array', 'min:1'],
            'store_order.*' => ['integer', 'exists:stores,id'],
        ]);

        $userId = $request->user()->id;

        UserStorePreference::updatePriorities($userId, $validated['store_order']);

        return response()->json([
            'success' => true,
            'message' => 'Store priorities updated',
        ]);
    }

    /**
     * Add a custom store to the registry.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addCustomStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'search_url_template' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:50'],
            'is_local' => ['nullable', 'boolean'],
        ]);

        $userId = $request->user()->id;

        // Clean up domain (remove http/https and trailing slashes)
        $domain = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $validated['domain']);
        $domain = rtrim($domain, '/');

        // Check if store already exists
        $existingStore = Store::findByDomain($domain);
        if ($existingStore) {
            // If store exists, just enable it for the user
            UserStorePreference::setPreference($userId, $existingStore->id, [
                'enabled' => true,
                'is_favorite' => true,
                'priority' => 100, // High priority for user-added stores
            ]);

            return response()->json([
                'success' => true,
                'message' => "'{$existingStore->name}' is already in the registry. It has been enabled for you.",
                'store' => [
                    'id' => $existingStore->id,
                    'name' => $existingStore->name,
                    'domain' => $existingStore->domain,
                    'is_new' => false,
                ],
            ]);
        }

        // Create new store
        $store = Store::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'domain' => $domain,
            'search_url_template' => $validated['search_url_template'] ?? null,
            'category' => $validated['category'] ?? Store::CATEGORY_SPECIALTY,
            'is_default' => false,
            'is_local' => $validated['is_local'] ?? false,
            'is_active' => true,
            'default_priority' => 50,
        ]);

        // Set user preference
        UserStorePreference::setPreference($userId, $store->id, [
            'enabled' => true,
            'is_favorite' => true,
            'priority' => 100,
        ]);

        return response()->json([
            'success' => true,
            'message' => "'{$store->name}' has been added to your stores.",
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'domain' => $store->domain,
                'category' => $store->category,
                'is_local' => $store->is_local,
                'has_search_template' => !empty($store->search_url_template),
                'is_new' => true,
            ],
        ]);
    }

    /**
     * Reset store preferences to defaults.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetStorePreferences(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = $request->user()->id;

        // Delete all user store preferences
        UserStorePreference::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store preferences have been reset to defaults.',
        ]);
    }
}
