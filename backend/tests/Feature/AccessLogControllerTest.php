<?php

describe('AccessLogController', function () {
    describe('index', function () {
        it('returns access logs for authorized user', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/access-logs')
                ->assertStatus(200)
                ->assertJsonStructure(['data']);
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/access-logs')
                ->assertStatus(401);
        });
    });

    describe('stats', function () {
        it('returns stats for authorized user', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/access-logs/stats')
                ->assertStatus(200)
                ->assertJsonIsObject();
        });
    });

    describe('deleteAll', function () {
        it('requires logs.delete permission (not just logs.view)', function () {
            // A user with only logs.view should not be able to delete
            $user = $this->actingAsUser();

            $this->deleteJson('/api/access-logs')
                ->assertStatus(403);
        });

        it('allows deletion for admin', function () {
            $this->actingAsAdmin();

            // Should succeed (200) or be blocked by HIPAA (422)
            $response = $this->deleteJson('/api/access-logs');

            expect($response->status())->toBeIn([200, 422]);
        });
    });
});
