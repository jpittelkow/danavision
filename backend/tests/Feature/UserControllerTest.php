<?php

use App\Models\User;

describe('UserController', function () {
    describe('index', function () {
        it('lists users for admin', function () {
            $this->actingAsAdmin();
            User::factory()->count(3)->create();

            $this->getJson('/api/users')
                ->assertStatus(200)
                ->assertJsonStructure(['data']);
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/users')
                ->assertStatus(401);
        });

        it('searches users by name', function () {
            $this->actingAsAdmin();
            User::factory()->create(['name' => 'Findable User']);
            User::factory()->create(['name' => 'Other Person']);

            $this->getJson('/api/users?search=Findable')
                ->assertStatus(200);
        });
    });

    describe('store', function () {
        it('creates a user as admin', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'Password123!',
            ])->assertStatus(201)
              ->assertJsonFragment(['name' => 'New User']);

            $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        });

        it('validates required fields', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/users', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password']);
        });

        it('rejects duplicate email', function () {
            $this->actingAsAdmin();
            $existing = User::factory()->create();

            $this->postJson('/api/users', [
                'name' => 'New User',
                'email' => $existing->email,
                'password' => 'Password123!',
            ])->assertStatus(422)
              ->assertJsonValidationErrors(['email']);
        });
    });

    describe('show', function () {
        it('returns user details for admin', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->getJson("/api/users/{$user->id}")
                ->assertStatus(200)
                ->assertJsonPath('user.id', $user->id);
        });

        it('hides sensitive fields', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $response = $this->getJson("/api/users/{$user->id}")
                ->assertStatus(200);

            $userData = $response->json('user');
            expect($userData)->not->toHaveKey('password');
            expect($userData)->not->toHaveKey('two_factor_secret');
        });
    });

    describe('update', function () {
        it('updates user name', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->putJson("/api/users/{$user->id}", [
                'name' => 'Updated Name',
            ])->assertStatus(200);

            expect($user->fresh()->name)->toBe('Updated Name');
        });
    });

    describe('destroy', function () {
        it('deletes a user', function () {
            $admin = $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->deleteJson("/api/users/{$user->id}")
                ->assertStatus(200);

            $this->assertDatabaseMissing('users', ['id' => $user->id]);
        });

        it('prevents deleting yourself', function () {
            $admin = $this->actingAsAdmin();

            $this->deleteJson("/api/users/{$admin->id}")
                ->assertStatus(400);
        });
    });

    describe('toggleAdmin', function () {
        it('grants admin status', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->postJson("/api/users/{$user->id}/toggle-admin")
                ->assertStatus(200);

            expect($user->fresh()->inGroup('admin'))->toBeTrue();
        });

        it('prevents removing own admin status', function () {
            $admin = $this->actingAsAdmin();

            $this->postJson("/api/users/{$admin->id}/toggle-admin")
                ->assertStatus(400);
        });
    });

    describe('toggleDisabled', function () {
        it('disables a user', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->postJson("/api/users/{$user->id}/disable")
                ->assertStatus(200);

            expect($user->fresh()->disabled_at)->not->toBeNull();
        });

        it('enables a disabled user', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create(['disabled_at' => now()]);

            $this->postJson("/api/users/{$user->id}/disable")
                ->assertStatus(200);

            expect($user->fresh()->disabled_at)->toBeNull();
        });

        it('prevents disabling yourself', function () {
            $admin = $this->actingAsAdmin();

            $this->postJson("/api/users/{$admin->id}/disable")
                ->assertStatus(400);
        });
    });

    describe('resetPassword', function () {
        it('resets a user password', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->postJson("/api/users/{$user->id}/reset-password", [
                'password' => 'NewPassword123!',
            ])->assertStatus(200);
        });

        it('validates password requirements', function () {
            $this->actingAsAdmin();
            $user = User::factory()->create();

            $this->postJson("/api/users/{$user->id}/reset-password", [
                'password' => '',
            ])->assertStatus(422);
        });
    });
});
