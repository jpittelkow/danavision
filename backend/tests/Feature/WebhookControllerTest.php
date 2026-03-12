<?php

use App\Models\Webhook;

describe('WebhookController', function () {
    describe('index', function () {
        it('lists webhooks for authorized user', function () {
            $this->actingAsAdmin();
            Webhook::factory()->count(2)->create();

            $this->getJson('/api/webhooks')
                ->assertStatus(200)
                ->assertJsonStructure(['webhooks']);
        });

        it('hides secret from index response', function () {
            $this->actingAsAdmin();
            Webhook::factory()->create(['secret' => 'my-secret']);

            $response = $this->getJson('/api/webhooks')
                ->assertStatus(200);

            $webhook = $response->json('webhooks.0');
            expect($webhook)->not->toHaveKey('secret');
            expect($webhook)->toHaveKey('secret_set');
        });
    });

    describe('store', function () {
        it('creates a webhook', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/webhooks', [
                'name' => 'Test Webhook',
                'url' => 'https://example.com/webhook',
                'events' => ['user.created'],
            ])->assertStatus(201)
              ->assertJsonFragment(['name' => 'Test Webhook']);
        });

        it('returns secret only on creation', function () {
            $this->actingAsAdmin();

            $response = $this->postJson('/api/webhooks', [
                'name' => 'Test Webhook',
                'url' => 'https://example.com/webhook',
                'events' => ['user.created'],
                'secret' => 'my-webhook-secret',
            ])->assertStatus(201);

            $webhook = $response->json('webhook');
            expect($webhook['secret'])->toBe('my-webhook-secret');
        });

        it('validates required fields', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/webhooks', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'url', 'events']);
        });

        it('rejects internal URLs (SSRF)', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/webhooks', [
                'name' => 'Internal',
                'url' => 'http://127.0.0.1/admin',
                'events' => ['user.created'],
            ])->assertStatus(422);
        });
    });

    describe('update', function () {
        it('updates a webhook', function () {
            $this->actingAsAdmin();
            $webhook = Webhook::factory()->create();

            $this->putJson("/api/webhooks/{$webhook->id}", [
                'name' => 'Updated Name',
            ])->assertStatus(200);

            expect($webhook->fresh()->name)->toBe('Updated Name');
        });

        it('hides secret from update response', function () {
            $this->actingAsAdmin();
            $webhook = Webhook::factory()->create(['secret' => 'original-secret']);

            $response = $this->putJson("/api/webhooks/{$webhook->id}", [
                'name' => 'Updated',
            ])->assertStatus(200);

            $data = $response->json('webhook');
            expect($data)->not->toHaveKey('secret');
            expect($data['secret_set'])->toBeTrue();
        });
    });

    describe('destroy', function () {
        it('deletes a webhook', function () {
            $this->actingAsAdmin();
            $webhook = Webhook::factory()->create();

            $this->deleteJson("/api/webhooks/{$webhook->id}")
                ->assertStatus(200);

            $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
        });
    });
});
