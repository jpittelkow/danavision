<?php

use App\Models\User;

describe('ProfileController', function () {
    describe('show', function () {
        it('returns current user profile', function () {
            $user = $this->actingAsUser();

            $this->getJson('/api/profile')
                ->assertStatus(200)
                ->assertJsonPath('user.id', $user->id);
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/profile')
                ->assertStatus(401);
        });
    });

    describe('update', function () {
        it('updates own name', function () {
            $user = $this->actingAsUser();

            $this->putJson('/api/profile', [
                'name' => 'New Name',
            ])->assertStatus(200);

            expect($user->fresh()->name)->toBe('New Name');
        });

        it('updates own email and resets verification', function () {
            $user = $this->actingAsUser(['email_verified_at' => now()]);

            $this->putJson('/api/profile', [
                'email' => 'newemail@example.com',
            ])->assertStatus(200)
              ->assertJsonPath('email_verification_sent', true);

            expect($user->fresh()->email)->toBe('newemail@example.com');
            expect($user->fresh()->email_verified_at)->toBeNull();
        });

        it('rejects invalid email', function () {
            $this->actingAsUser();

            $this->putJson('/api/profile', [
                'email' => 'not-an-email',
            ])->assertStatus(422);
        });
    });

    describe('updatePassword', function () {
        it('updates password with correct current password', function () {
            $this->actingAsUser(['password' => 'OldPassword123!']);

            $this->putJson('/api/profile/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertStatus(200);
        });

        it('rejects wrong current password', function () {
            $this->actingAsUser(['password' => 'OldPassword123!']);

            $this->putJson('/api/profile/password', [
                'current_password' => 'WrongPassword',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertStatus(422);
        });
    });

    describe('destroy', function () {
        it('deletes own account with correct password', function () {
            $user = $this->actingAsUser(['password' => 'Password123!']);

            $this->deleteJson('/api/profile', [
                'password' => 'Password123!',
            ])->assertStatus(200);

            $this->assertDatabaseMissing('users', ['id' => $user->id]);
        });

        it('rejects deletion with wrong password', function () {
            $this->actingAsUser(['password' => 'Password123!']);

            $this->deleteJson('/api/profile', [
                'password' => 'WrongPassword',
            ])->assertStatus(422);
        });
    });
});
