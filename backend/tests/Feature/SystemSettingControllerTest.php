<?php

describe('SystemSettingController', function () {
    describe('public', function () {
        it('returns public settings without auth', function () {
            $this->getJson('/api/system-settings/public')
                ->assertStatus(200)
                ->assertJsonIsObject();
        });
    });

    describe('index', function () {
        it('returns all system settings for admin', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/system-settings')
                ->assertStatus(200)
                ->assertJsonIsObject();
        });
    });

    describe('showGroup', function () {
        it('returns 404 for unknown group', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/system-settings/nonexistent_group')
                ->assertStatus(404);
        });
    });

    describe('update', function () {
        it('rejects unknown settings key', function () {
            $this->actingAsAdmin();

            $this->putJson('/api/system-settings', [
                'settings' => [
                    ['group' => 'general', 'key' => 'totally_fake_key', 'value' => 'bad'],
                ],
            ])->assertStatus(422);
        });

        it('rejects unauthenticated access', function () {
            $this->putJson('/api/system-settings', [
                'settings' => [],
            ])->assertStatus(401);
        });
    });
});
