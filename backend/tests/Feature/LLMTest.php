<?php

use App\Models\User;
use App\Models\AIProvider;

describe('LLM API', function () {
    
    describe('Get Providers', function () {
        it('returns available LLM providers', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/llm/providers');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'providers',
                ]);
        });
    });

    describe('Get Config', function () {
        it('returns user LLM configuration', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/llm/config');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'mode',
                    'providers',
                ]);
        });
    });

    describe('Update Config', function () {
        it('can update LLM mode', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->putJson('/api/llm/config', [
                    'mode' => 'aggregation',
                ]);

            $response->assertStatus(200);
        });
    });

    describe('Test Provider', function () {
        it('requires provider to be configured', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/llm/test/claude');

            // Should fail because no API key is configured
            $response->assertStatus(400);
        });
    });

    describe('Query', function () {
        it('requires at least one configured provider', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/llm/query', [
                    'prompt' => 'Hello, world!',
                ]);

            // Should fail because no provider is configured
            $response->assertStatus(400);
        });

        it('validates prompt is required', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/llm/query', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['prompt']);
        });
    });

    describe('Vision Query', function () {
        it('validates image is required for vision query', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/llm/query/vision', [
                    'prompt' => 'What is in this image?',
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['image']);
        });
    });
});
