<?php

use App\Models\AIProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('users can view AI providers in settings page', function () {
    $user = User::factory()->create();

    // Note: /settings/ai now redirects to /settings
    $response = $this->actingAs($user)->get('/settings');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Settings')
        ->has('providers')
        ->has('availableProviders')
        ->has('providerInfo')
    );
});

test('users can add a new AI provider', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/settings/ai', [
        'provider' => 'claude',
        'api_key' => 'sk-ant-test-key-12345',
        'model' => 'claude-sonnet-4-20250514',
        'is_primary' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ai_providers', [
        'user_id' => $user->id,
        'provider' => 'claude',
        'model' => 'claude-sonnet-4-20250514',
        'is_primary' => true,
    ]);
});

test('users cannot add duplicate providers', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->create();

    $response = $this->actingAs($user)->post('/settings/ai', [
        'provider' => 'claude',
        'api_key' => 'sk-another-key',
    ]);

    $response->assertSessionHasErrors('provider');
    $this->assertDatabaseCount('ai_providers', 1);
});

test('users can update AI provider settings', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create([
        'model' => 'claude-3-haiku-20240307',
    ]);

    $response = $this->actingAs($user)->patch("/ai-providers/{$provider->id}", [
        'model' => 'claude-sonnet-4-20250514',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $provider->refresh();
    $this->assertEquals('claude-sonnet-4-20250514', $provider->model);
});

test('users cannot update other users providers', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $provider = AIProvider::factory()->for($otherUser)->claude()->create();

    $response = $this->actingAs($user)->patch("/ai-providers/{$provider->id}", [
        'model' => 'claude-sonnet-4-20250514',
    ]);

    $response->assertStatus(403);
});

test('users can delete AI providers', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create();

    $response = $this->actingAs($user)->delete("/ai-providers/{$provider->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
});

test('users can set a provider as primary', function () {
    $user = User::factory()->create();
    $provider1 = AIProvider::factory()->for($user)->claude()->primary()->create();
    $provider2 = AIProvider::factory()->for($user)->openai()->create();

    $response = $this->actingAs($user)->post("/ai-providers/{$provider2->id}/primary");

    $response->assertRedirect();
    $provider1->refresh();
    $provider2->refresh();
    
    $this->assertFalse($provider1->is_primary);
    $this->assertTrue($provider2->is_primary);
});

test('users can toggle provider active status', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create(['is_active' => true]);

    $response = $this->actingAs($user)->post("/ai-providers/{$provider->id}/toggle");

    $response->assertRedirect();
    $provider->refresh();
    $this->assertFalse($provider->is_active);
});

test('api key is encrypted in database', function () {
    $user = User::factory()->create();
    $apiKey = 'sk-test-secret-key-12345';

    $this->actingAs($user)->post('/settings/ai', [
        'provider' => 'openai',
        'api_key' => $apiKey,
    ]);

    $provider = AIProvider::where('user_id', $user->id)->first();
    
    // Raw value should not match the plain key
    $this->assertNotEquals($apiKey, $provider->api_key);
    
    // Decrypted value should match
    $this->assertEquals($apiKey, $provider->getDecryptedApiKey());
});

test('masked api key only shows last 4 characters', function () {
    $user = User::factory()->create();
    $provider = AIProvider::factory()->for($user)->claude()->create([
        'api_key' => 'sk-ant-test-key-12345',
    ]);

    $masked = $provider->getMaskedApiKey();
    
    $this->assertStringEndsWith('2345', $masked);
    $this->assertStringStartsWith('•', $masked);
});

test('available providers are filtered correctly', function () {
    $user = User::factory()->create();
    AIProvider::factory()->for($user)->claude()->create();
    AIProvider::factory()->for($user)->openai()->create();

    $response = $this->actingAs($user)->get('/settings');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('availableProviders', fn ($providers) => 
            collect($providers)->pluck('provider')->doesntContain('claude') &&
            collect($providers)->pluck('provider')->doesntContain('openai') &&
            collect($providers)->pluck('provider')->contains('gemini') &&
            collect($providers)->pluck('provider')->contains('local')
        )
    );
});
