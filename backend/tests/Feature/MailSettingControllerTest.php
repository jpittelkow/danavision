<?php

describe('MailSettingController', function () {
    describe('index', function () {
        it('returns mail settings for admin', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/mail-settings')
                ->assertStatus(200)
                ->assertJsonIsObject();
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/mail-settings')
                ->assertStatus(401);
        });
    });

    describe('test', function () {
        it('validates email address', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/mail-settings/test', [
                'to' => 'not-an-email',
            ])->assertStatus(422);
        });

        it('requires to field', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/mail-settings/test', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['to']);
        });
    });

    describe('resetKey', function () {
        it('rejects unknown frontend key', function () {
            $this->actingAsAdmin();

            $this->deleteJson('/api/mail-settings/keys/totally_fake_key')
                ->assertStatus(422);
        });
    });
});
