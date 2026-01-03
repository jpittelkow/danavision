<?php

namespace App\Http\Controllers;

use App\Models\AIPrompt;
use App\Models\AIProvider;
use App\Models\DefaultPrompts;
use App\Services\AI\AIService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AIProviderController extends Controller
{
    /**
     * Display AI provider settings.
     */
    public function index(Request $request): Response
    {
        $providers = $request->user()->aiProviders()
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (AIProvider $provider) {
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
                    'available_models' => $provider->getAvailableModels(),
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

        // Get AI prompts (custom + defaults)
        $prompts = AIPrompt::getAllForUser($request->user()->id);

        return Inertia::render('Settings/AI', [
            'providers' => $providers,
            'availableProviders' => $availableProviders,
            'providerInfo' => AIProvider::$providers,
            'prompts' => $prompts,
        ]);
    }

    /**
     * Store a new AI provider.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:claude,openai,gemini,local'],
            'api_key' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url'],
            'is_active' => ['boolean'],
            'is_primary' => ['boolean'],
        ]);

        // Check if provider already exists for user
        $existing = AIProvider::where('user_id', $request->user()->id)
            ->where('provider', $validated['provider'])
            ->first();

        if ($existing) {
            return back()->withErrors(['provider' => 'You already have this provider configured.']);
        }

        // Get default model if not provided
        $providerInfo = AIProvider::$providers[$validated['provider']] ?? null;
        $model = $validated['model'] ?? ($providerInfo['default_model'] ?? null);
        $baseUrl = $validated['base_url'] ?? ($providerInfo['default_base_url'] ?? null);

        $provider = AIProvider::create([
            'user_id' => $request->user()->id,
            'provider' => $validated['provider'],
            'api_key' => $validated['api_key'] ?? null,
            'model' => $model,
            'base_url' => $baseUrl,
            'is_active' => $validated['is_active'] ?? true,
            'is_primary' => $validated['is_primary'] ?? false,
        ]);

        // If set as primary, update other providers
        if ($provider->is_primary) {
            AIProvider::where('user_id', $request->user()->id)
                ->where('id', '!=', $provider->id)
                ->update(['is_primary' => false]);
        }

        return back()->with('success', 'AI provider added successfully.');
    }

    /**
     * Update an AI provider.
     */
    public function update(Request $request, AIProvider $provider): RedirectResponse
    {
        // Ensure user owns this provider
        if ($provider->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'api_key' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'base_url' => ['nullable', 'string', 'url'],
            'is_active' => ['boolean'],
        ]);

        // Only update api_key if a new one is provided (not empty string)
        $updateData = [
            'model' => $validated['model'] ?? $provider->model,
            'base_url' => $validated['base_url'] ?? $provider->base_url,
            'is_active' => $validated['is_active'] ?? $provider->is_active,
        ];

        // Only update API key if provided and not empty
        if (!empty($validated['api_key'])) {
            $updateData['api_key'] = $validated['api_key'];
            // Reset test status when key changes
            $updateData['test_status'] = AIProvider::STATUS_UNTESTED;
            $updateData['last_tested_at'] = null;
            $updateData['test_error'] = null;
        }

        $provider->update($updateData);

        return back()->with('success', 'AI provider updated successfully.');
    }

    /**
     * Delete an AI provider.
     */
    public function destroy(Request $request, AIProvider $provider): RedirectResponse
    {
        // Ensure user owns this provider
        if ($provider->user_id !== $request->user()->id) {
            abort(403);
        }

        $wasPrimary = $provider->is_primary;
        $provider->delete();

        // If deleted provider was primary, set another as primary
        if ($wasPrimary) {
            $newPrimary = AIProvider::where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->first();
            
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return back()->with('success', 'AI provider removed successfully.');
    }

    /**
     * Set a provider as primary.
     */
    public function setPrimary(Request $request, AIProvider $provider): RedirectResponse
    {
        // Ensure user owns this provider
        if ($provider->user_id !== $request->user()->id) {
            abort(403);
        }

        $provider->setAsPrimary();

        return back()->with('success', $provider->getDisplayName() . ' is now your primary AI provider.');
    }

    /**
     * Toggle provider active status.
     */
    public function toggleActive(Request $request, AIProvider $provider): RedirectResponse
    {
        // Ensure user owns this provider
        if ($provider->user_id !== $request->user()->id) {
            abort(403);
        }

        $provider->update(['is_active' => !$provider->is_active]);

        $status = $provider->is_active ? 'enabled' : 'disabled';
        return back()->with('success', $provider->getDisplayName() . " has been {$status}.");
    }

    /**
     * Test an AI provider's API connection.
     */
    public function test(Request $request, AIProvider $provider): RedirectResponse
    {
        // Ensure user owns this provider
        if ($provider->user_id !== $request->user()->id) {
            abort(403);
        }

        $service = AIService::fromProvider($provider);
        $result = $service->testConnection();

        $provider->markAsTested($result['success'], $result['message']);

        if ($result['success']) {
            return back()->with('success', $provider->getDisplayName() . ' connection test passed!');
        }

        return back()->with('error', 'Connection test failed: ' . $result['message']);
    }

    /**
     * Fetch available Ollama models.
     */
    public function ollamaModels(Request $request): \Illuminate\Http\JsonResponse
    {
        $baseUrl = $request->input('base_url', 'http://localhost:11434');
        $models = AIService::listOllamaModels($baseUrl);

        return response()->json([
            'models' => $models,
            'available' => !empty($models),
        ]);
    }

    /**
     * Update an AI prompt.
     */
    public function updatePrompt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt_type' => ['required', 'string', 'in:' . implode(',', AIPrompt::getTypes())],
            'prompt_text' => ['required', 'string', 'min:10', 'max:10000'],
        ]);

        AIPrompt::setPrompt(
            $validated['prompt_type'],
            $validated['prompt_text'],
            $request->user()->id
        );

        return back()->with('success', 'AI prompt updated successfully.');
    }

    /**
     * Reset an AI prompt to default.
     */
    public function resetPrompt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prompt_type' => ['required', 'string', 'in:' . implode(',', AIPrompt::getTypes())],
        ]);

        AIPrompt::resetPrompt($validated['prompt_type'], $request->user()->id);

        return back()->with('success', 'AI prompt reset to default.');
    }
}
