<?php

use App\Models\User;

describe('Two-Factor Authentication', function () {
    
    describe('2FA Status', function () {
        it('returns 2FA status for authenticated user', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/auth/2fa/status');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'enabled',
                    'confirmed',
                ]);
        });
    });

    describe('Enable 2FA', function () {
        it('can initiate 2FA setup', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/auth/2fa/enable');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'secret',
                    'qr_code',
                ]);
        });

        it('returns existing setup if already started', function () {
            $user = User::factory()->create([
                'two_factor_secret' => encrypt('TESTSECRET'),
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/auth/2fa/enable');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'secret',
                    'qr_code',
                ]);
        });
    });

    describe('Confirm 2FA', function () {
        it('validates the TOTP code on confirm', function () {
            // Use a valid Base32 TOTP secret (16 characters, A-Z and 2-7)
            $user = User::factory()->create([
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/auth/2fa/confirm', [
                    'code' => '123456',
                ]);

            // Invalid code should fail
            $response->assertStatus(400);
        });
    });

    describe('Disable 2FA', function () {
        it('can disable 2FA', function () {
            $user = User::factory()->create([
                'password' => 'Password123!',
                'two_factor_enabled' => true,
                'two_factor_secret' => encrypt('TESTSECRET'),
                'two_factor_confirmed_at' => now(),
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/auth/2fa/disable');

            $response->assertStatus(200);
        });
    });

    describe('Recovery Codes', function () {
        it('can retrieve recovery codes when 2FA is enabled', function () {
            $user = User::factory()->create([
                'password' => 'Password123!',
                'two_factor_enabled' => true,
                'two_factor_secret' => encrypt('TESTSECRET'),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => ['CODE-0001', 'CODE-0002'],
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/auth/2fa/recovery-codes');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'recovery_codes',
                ]);
        });
    });
});
