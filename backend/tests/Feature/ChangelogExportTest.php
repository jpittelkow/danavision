<?php

use App\Models\User;

describe('Changelog Export', function () {

    describe('GET /api/changelog/versions', function () {
        it('returns available versions for authenticated user', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/changelog/versions');

            $response->assertStatus(200)
                ->assertJsonStructure(['versions']);

            $versions = $response->json('versions');
            expect($versions)->toBeArray();

            // Versions should not include "Unreleased"
            expect($versions)->not->toContain('Unreleased');
        });

        it('rejects unauthenticated requests', function () {
            $response = $this->getJson('/api/changelog/versions');

            $response->assertStatus(401);
        });
    });

    describe('GET /api/changelog/export', function () {
        it('returns a markdown file for a valid version range', function () {
            $user = User::factory()->create();

            // Get available versions first
            $versionsResponse = $this->actingAs($user, 'sanctum')
                ->getJson('/api/changelog/versions');

            $versions = $versionsResponse->json('versions');

            if (count($versions) < 2) {
                $this->markTestSkipped('Need at least 2 changelog versions to test export');
            }

            $to = $versions[0]; // newest
            $from = $versions[count($versions) - 1]; // oldest

            $response = $this->actingAs($user, 'sanctum')
                ->get('/api/changelog/export?' . http_build_query(['from' => $from, 'to' => $to]));

            $response->assertStatus(200);

            expect($response->headers->get('Content-Type'))->toContain('text/markdown');
            expect($response->headers->get('Content-Disposition'))->toContain('attachment');

            $content = $response->streamedContent();
            expect($content)->toContain('Upgrade Guide');
            expect($content)->toContain($from);
            expect($content)->toContain($to);
        });

        it('validates required parameters', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/changelog/export');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['from', 'to']);
        });

        it('rejects invalid versions', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/changelog/export?' . http_build_query([
                    'from' => '99.99.99',
                    'to' => '100.0.0',
                ]));

            $response->assertStatus(422);
        });

        it('rejects when from is not older than to', function () {
            $user = User::factory()->create();

            $versionsResponse = $this->actingAs($user, 'sanctum')
                ->getJson('/api/changelog/versions');

            $versions = $versionsResponse->json('versions');

            if (count($versions) < 2) {
                $this->markTestSkipped('Need at least 2 changelog versions to test export');
            }

            // Swap: from is newest, to is oldest (wrong order)
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/changelog/export?' . http_build_query([
                    'from' => $versions[0],
                    'to' => $versions[count($versions) - 1],
                ]));

            $response->assertStatus(422);
        });

        it('rejects unauthenticated requests', function () {
            $response = $this->getJson('/api/changelog/export?' . http_build_query([
                'from' => '0.1.0',
                'to' => '0.2.0',
            ]));

            $response->assertStatus(401);
        });
    });
});
