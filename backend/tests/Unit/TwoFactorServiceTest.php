<?php

use App\Services\Auth\TwoFactorService;
use App\Models\User;

describe('TwoFactorService', function () {
    
    beforeEach(function () {
        $this->service = new TwoFactorService();
    });

    describe('generateSecret', function () {
        it('generates a valid secret and QR code for a user', function () {
            $user = User::factory()->create();
            
            $result = $this->service->generateSecret($user);

            expect($result)->toBeArray();
            expect($result)->toHaveKey('secret');
            expect($result)->toHaveKey('qr_code');
            expect($result['secret'])->toBeString();
            expect(strlen($result['secret']))->toBeGreaterThanOrEqual(16);
        });

        it('stores the secret on the user (unconfirmed)', function () {
            $user = User::factory()->create();
            
            $this->service->generateSecret($user);
            
            $user->refresh();
            expect($user->two_factor_secret)->not->toBeNull();
            expect($user->two_factor_enabled)->toBeFalse();
            expect($user->two_factor_confirmed_at)->toBeNull();
        });
    });

    describe('verifyCode', function () {
        it('returns false when user has no 2FA secret', function () {
            $user = User::factory()->create(['two_factor_secret' => null]);

            $result = $this->service->verifyCode($user, '123456');

            expect($result)->toBeFalse();
        });
    });

    describe('confirmSetup', function () {
        it('enables 2FA and generates recovery codes', function () {
            $user = User::factory()->create();
            $this->service->generateSecret($user);
            
            $codes = $this->service->confirmSetup($user);
            
            $user->refresh();
            expect($codes)->toBeArray();
            expect(count($codes))->toBe(10);
            expect($user->two_factor_enabled)->toBeTrue();
            expect($user->two_factor_confirmed_at)->not->toBeNull();
        });
    });

    describe('disable', function () {
        it('disables 2FA and clears all related fields', function () {
            $user = User::factory()->create([
                'two_factor_enabled' => true,
                'two_factor_secret' => encrypt('secret'),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => ['code1', 'code2'],
            ]);
            
            $this->service->disable($user);
            
            $user->refresh();
            expect($user->two_factor_enabled)->toBeFalse();
            expect($user->two_factor_secret)->toBeNull();
            expect($user->two_factor_confirmed_at)->toBeNull();
            expect($user->two_factor_recovery_codes)->toBeNull();
        });
    });
});
