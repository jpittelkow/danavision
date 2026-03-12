<?php

describe('SettingController', function () {
    describe('index', function () {
        it('returns settings for authorized user', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/settings')
                ->assertStatus(200);
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/settings')
                ->assertStatus(401);
        });
    });

    describe('update', function () {
        it('rejects unknown settings group', function () {
            $this->actingAsAdmin();

            $this->putJson('/api/settings', [
                'settings' => [
                    ['group' => 'nonexistent_group', 'key' => 'foo', 'value' => 'bar'],
                ],
            ])->assertStatus(422);
        });
    });

    describe('showGroup', function () {
        it('returns settings for a valid group', function () {
            $this->actingAsAdmin();
            $schema = config('user-settings-schema');
            if (empty($schema)) {
                $this->markTestSkipped('No user settings schema defined');
            }
            $group = $schema[0] ?? null;
            if (!$group) {
                $this->markTestSkipped('No groups in schema');
            }

            $this->getJson("/api/settings/{$group}")
                ->assertStatus(200);
        });
    });

    describe('updateGroup', function () {
        it('rejects unknown group', function () {
            $this->actingAsAdmin();

            $this->putJson('/api/settings/totally_fake_group', [
                'settings' => [
                    ['key' => 'foo', 'value' => 'bar'],
                ],
            ])->assertStatus(422);
        });
    });
});
