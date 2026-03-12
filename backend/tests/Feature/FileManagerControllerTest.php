<?php

describe('FileManagerController', function () {
    describe('index', function () {
        it('lists files for admin', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files')
                ->assertStatus(200);
        });

        it('rejects unauthenticated access', function () {
            $this->getJson('/api/storage/files')
                ->assertStatus(401);
        });

        it('rejects path traversal with double dots', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=../../etc/passwd')
                ->assertStatus(422);
        });

        it('rejects null byte in path', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=test%00.php')
                ->assertStatus(422);
        });

        it('rejects .env path segment', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=.env')
                ->assertStatus(422);
        });

        it('rejects .git path segment', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=.git/config')
                ->assertStatus(422);
        });

        it('rejects config path segment', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=config/app.php')
                ->assertStatus(422);
        });

        it('rejects vendor path segment', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=vendor/autoload.php')
                ->assertStatus(422);
        });

        it('rejects bootstrap path segment', function () {
            $this->actingAsAdmin();

            $this->getJson('/api/storage/files?path=bootstrap/app.php')
                ->assertStatus(422);
        });
    });

    describe('upload', function () {
        it('requires files array', function () {
            $this->actingAsAdmin();

            $this->postJson('/api/storage/files', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['files']);
        });
    });

    describe('rename', function () {
        it('rejects names with path traversal', function () {
            $this->actingAsAdmin();

            $this->putJson('/api/storage/files/test.txt/rename', [
                'name' => '../malicious.txt',
            ])->assertStatus(422);
        });

        it('rejects names with slashes', function () {
            $this->actingAsAdmin();

            $this->putJson('/api/storage/files/test.txt/rename', [
                'name' => 'path/file.txt',
            ])->assertStatus(422);
        });
    });
});
