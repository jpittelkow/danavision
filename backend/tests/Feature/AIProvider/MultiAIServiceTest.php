<?php

use App\Models\AIProvider;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\MultiAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('multi ai service can be created for user', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->create();

    $service = MultiAIService::forUser($user->id);

    $this->assertInstanceOf(MultiAIService::class, $service);
    $this->assertTrue($service->isAvailable());
});

test('multi ai service is not available without providers', function () {
    $user = User::factory()->create();

    $service = MultiAIService::forUser($user->id);

    $this->assertFalse($service->isAvailable());
    $this->assertEquals(0, $service->getProviderCount());
});

test('multi ai service only uses active providers', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->create(['is_active' => true]);
    AIProvider::factory()->for($user)->openai()->create(['is_active' => false]);

    $service = MultiAIService::forUser($user->id);

    $this->assertEquals(1, $service->getProviderCount());
});

test('multi ai service returns primary provider', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->create();
    $primary = AIProvider::factory()->for($user)->openai()->primary()->create();

    $service = MultiAIService::forUser($user->id);

    $this->assertNotNull($service->getPrimaryProvider());
    $this->assertEquals($primary->id, $service->getPrimaryProvider()->id);
});

test('ai provider marks test as successful', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create([
        'test_status' => AIProvider::STATUS_UNTESTED,
    ]);

    $provider->markAsTested(true);

    $provider->refresh();
    $this->assertEquals(AIProvider::STATUS_SUCCESS, $provider->test_status);
    $this->assertNotNull($provider->last_tested_at);
    $this->assertNull($provider->test_error);
});

test('ai provider marks test as failed with error', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create();

    $provider->markAsTested(false, 'Invalid API key');

    $provider->refresh();
    $this->assertEquals(AIProvider::STATUS_FAILED, $provider->test_status);
    $this->assertEquals('Invalid API key', $provider->test_error);
});

test('ai service can be created from provider', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create([
        'api_key' => 'sk-test-key',
        'model' => 'claude-sonnet-4-20250514',
    ]);

    $service = AIService::fromProvider($provider);

    $this->assertInstanceOf(AIService::class, $service);
    $this->assertEquals('claude', $service->getProviderType());
    $this->assertEquals('claude-sonnet-4-20250514', $service->getModel());
});

test('ai service for user returns primary provider service', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->create(['api_key' => 'key1']);
    AIProvider::factory()->for($user)->openai()->primary()->create(['api_key' => 'key2']);

    $service = AIService::forUser($user->id);

    $this->assertEquals('openai', $service->getProviderType());
});

test('get provider status returns correct information', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->primary()->tested()->create();
    AIProvider::factory()->for($user)->openai()->create();

    $service = MultiAIService::forUser($user->id);
    $status = $service->getProviderStatus();

    $this->assertArrayHasKey('claude', $status);
    $this->assertArrayHasKey('openai', $status);
    $this->assertTrue($status['claude']['is_primary']);
    $this->assertEquals(AIProvider::STATUS_SUCCESS, $status['claude']['test_status']);
});

test('ollama models can be listed', function () {
    Http::fake([
        'localhost:11434/api/tags' => Http::response([
            'models' => [
                ['name' => 'llama3.2', 'size' => 1000000],
                ['name' => 'mistral', 'size' => 2000000],
            ],
        ]),
    ]);

    $models = AIService::listOllamaModels();

    $this->assertCount(2, $models);
    $this->assertEquals('llama3.2', $models[0]['name']);
});

test('ollama models returns empty array on connection failure', function () {
    Http::fake([
        'localhost:11434/api/tags' => Http::response([], 500),
    ]);

    $models = AIService::listOllamaModels();

    $this->assertEmpty($models);
});
