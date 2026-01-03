<?php

use App\Models\AIProvider;
use App\Models\User;
use App\Services\AI\AIModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('returns default Claude models without API key', function () {
    $service = new AIModelService();
    $models = $service->getClaudeModels(null);

    $this->assertIsArray($models);
    $this->assertArrayHasKey('claude-sonnet-4-20250514', $models);
    $this->assertArrayHasKey('claude-3-5-sonnet-20241022', $models);
});

test('returns default OpenAI models without API key', function () {
    $service = new AIModelService();
    $models = $service->getOpenAIModels(null);

    $this->assertIsArray($models);
    $this->assertArrayHasKey('gpt-4o', $models);
    $this->assertArrayHasKey('gpt-4o-mini', $models);
});

test('returns default Gemini models without API key', function () {
    $service = new AIModelService();
    $models = $service->getGeminiModels(null);

    $this->assertIsArray($models);
    $this->assertArrayHasKey('gemini-2.0-flash', $models);
    $this->assertArrayHasKey('gemini-1.5-pro', $models);
    $this->assertArrayHasKey('gemini-1.5-flash', $models);
    // Deprecated model should not be present
    $this->assertArrayNotHasKey('gemini-pro', $models);
});

test('returns default local models without base URL', function () {
    $service = new AIModelService();
    $models = $service->getLocalModels(null);

    $this->assertIsArray($models);
    $this->assertArrayHasKey('llama3.2', $models);
    $this->assertArrayHasKey('mistral', $models);
});

test('fetches Gemini models dynamically when API key is provided', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'displayName' => 'Gemini 2.0 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
                [
                    'name' => 'models/gemini-1.5-pro',
                    'displayName' => 'Gemini 1.5 Pro',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
                [
                    'name' => 'models/embedding-001',
                    'displayName' => 'Embedding 001',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ], 200),
    ]);

    $service = new AIModelService();
    $models = $service->getGeminiModels('test-api-key');

    $this->assertArrayHasKey('gemini-2.0-flash', $models);
    $this->assertArrayHasKey('gemini-1.5-pro', $models);
    // Embedding model should be filtered out (doesn't support generateContent)
    $this->assertArrayNotHasKey('embedding-001', $models);
});

test('falls back to default Gemini models on API error', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Invalid key'], 401),
    ]);

    $service = new AIModelService();
    $models = $service->getGeminiModels('invalid-api-key');

    // Should return defaults on error
    $this->assertArrayHasKey('gemini-2.0-flash', $models);
    $this->assertArrayHasKey('gemini-1.5-pro', $models);
});

test('caches Gemini models for performance', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'displayName' => 'Gemini 2.0 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ], 200),
    ]);

    $service = new AIModelService();
    
    // First call should hit the API
    $models1 = $service->getGeminiModels('test-api-key');
    
    // Second call should use cache
    $models2 = $service->getGeminiModels('test-api-key');

    // API should only be called once
    Http::assertSentCount(1);
    $this->assertEquals($models1, $models2);
});

test('clears cache properly', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'displayName' => 'Gemini 2.0 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ], 200),
    ]);

    $service = new AIModelService();
    
    // First call
    $service->getGeminiModels('test-api-key', 'https://generativelanguage.googleapis.com/v1beta');
    
    // Clear cache
    $service->clearCache('gemini', 'test-api-key', 'https://generativelanguage.googleapis.com/v1beta');
    
    // Second call should hit API again
    $service->getGeminiModels('test-api-key', 'https://generativelanguage.googleapis.com/v1beta');

    Http::assertSentCount(2);
});

test('getModelsForProvider returns correct models for each provider type', function () {
    $user = User::factory()->create();
    
    $claudeProvider = AIProvider::factory()->for($user)->claude()->create();
    $geminiProvider = AIProvider::factory()->for($user)->gemini()->create();

    $service = new AIModelService();

    $claudeModels = $service->getModelsForProvider($claudeProvider);
    $this->assertArrayHasKey('claude-sonnet-4-20250514', $claudeModels);

    $geminiModels = $service->getModelsForProvider($geminiProvider);
    $this->assertArrayHasKey('gemini-2.0-flash', $geminiModels);
});

test('users can fetch models for their provider via API', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->gemini()->create();

    $response = $this->actingAs($user)->get("/ai-providers/{$provider->id}/models");

    $response->assertStatus(200);
    $response->assertJsonStructure(['models', 'provider']);
    $response->assertJsonPath('provider', 'gemini');
});

test('users cannot fetch models for other users providers', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $provider = AIProvider::factory()->for($otherUser)->gemini()->create();

    $response = $this->actingAs($user)->get("/ai-providers/{$provider->id}/models");

    $response->assertStatus(403);
});

test('users can refresh cached models for their provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->gemini()->create();

    $response = $this->actingAs($user)->post("/ai-providers/{$provider->id}/models/refresh");

    $response->assertStatus(200);
    $response->assertJsonStructure(['models', 'provider']);
});

test('Gemini provider has default base URL configured', function () {
    $providerInfo = AIProvider::$providers[AIProvider::PROVIDER_GEMINI];

    $this->assertArrayHasKey('default_base_url', $providerInfo);
    $this->assertEquals('https://generativelanguage.googleapis.com/v1beta', $providerInfo['default_base_url']);
});

test('Gemini models list includes current models and excludes deprecated ones', function () {
    $providerInfo = AIProvider::$providers[AIProvider::PROVIDER_GEMINI];
    $models = $providerInfo['models'];

    // Current models should be present
    $this->assertArrayHasKey('gemini-2.0-flash', $models);
    $this->assertArrayHasKey('gemini-1.5-pro', $models);
    $this->assertArrayHasKey('gemini-1.5-flash', $models);
    $this->assertArrayHasKey('gemini-2.5-flash-preview-05-20', $models);

    // Deprecated model should not be present
    $this->assertArrayNotHasKey('gemini-pro', $models);
});

test('default Gemini model is set to current model', function () {
    $providerInfo = AIProvider::$providers[AIProvider::PROVIDER_GEMINI];

    $this->assertEquals('gemini-2.0-flash', $providerInfo['default_model']);
});
