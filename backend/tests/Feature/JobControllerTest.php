<?php

describe('JobController', function () {
    describe('scheduled', function () {
        it('lists scheduled tasks for authorized user', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/jobs/scheduled')
                ->assertStatus(200)
                ->assertJsonStructure(['tasks']);
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/jobs/scheduled')
                ->assertStatus(401);
        });
    });

    describe('run', function () {
        it('rejects non-whitelisted command', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/jobs/run/some:fake:command')
                ->assertStatus(403);
        });

        it('rejects unauthenticated access', function () {
            $this->postJson('/api/jobs/run/cache:clear')
                ->assertStatus(401);
        });
    });

    describe('queue', function () {
        it('returns queue status for admin', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/jobs/queue')
                ->assertStatus(200)
                ->assertJsonIsObject();
        });
    });

    describe('failed', function () {
        it('returns failed jobs list', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/jobs/failed')
                ->assertStatus(200)
                ->assertJsonStructure(['data']);
        });

        it('returns 404 for non-existent failed job retry', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/jobs/failed/99999/retry')
                ->assertStatus(404);
        });
    });
});
