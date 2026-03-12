<?php

use App\Models\User;

describe('Backup & Restore', function () {
    
    describe('List Backups', function () {
        it('requires admin privileges', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/backup');

            $response->assertStatus(403);
        });

        it('returns backup list for admin', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/backup');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'backups',
                ]);
        });
    });

    describe('Create Backup', function () {
        it('requires admin privileges', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/backup/create');

            $response->assertStatus(403);
        });

        it('can create backup as admin', function () {
            $user = createAdminUser();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/backup/create');

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'backup' => ['filename', 'size'],
                ]);
        });
    });

    describe('Download Backup', function () {
        it('requires admin privileges for download', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->get('/api/backup/download/test.zip');

            $response->assertStatus(403);
        });
    });

    describe('Restore Backup', function () {
        it('requires admin privileges for restore', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/backup/restore', [
                    'filename' => 'test.zip',
                ]);

            $response->assertStatus(403);
        });
    });

    describe('Delete Backup', function () {
        it('requires admin privileges for deletion', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->deleteJson('/api/backup/test.zip');

            $response->assertStatus(403);
        });
    });
});
