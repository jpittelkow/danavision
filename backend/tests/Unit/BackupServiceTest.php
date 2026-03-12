<?php

use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Storage;

describe('BackupService', function () {
    
    beforeEach(function () {
        // Setup test storage disk BEFORE creating the service
        Storage::fake('backups');
        config(['backup.disk' => 'backups']);
        
        $this->service = new BackupService();
    });

    describe('listBackups', function () {
        it('returns empty array when no backups exist', function () {
            $backups = $this->service->listBackups();

            expect($backups)->toBeArray();
            expect($backups)->toBeEmpty();
        });

        it('returns only zip files', function () {
            Storage::disk('backups')->put('backup1.zip', 'content');
            Storage::disk('backups')->put('notabackup.txt', 'content');

            $backups = $this->service->listBackups();

            expect(count($backups))->toBe(1);
            expect($backups[0]['filename'])->toBe('backup1.zip');
        });

        it('returns backups sorted by date descending', function () {
            Storage::disk('backups')->put('backup1.zip', 'old');
            sleep(1);
            Storage::disk('backups')->put('backup2.zip', 'new');

            $backups = $this->service->listBackups();

            expect($backups[0]['filename'])->toBe('backup2.zip');
        });
    });

    describe('create', function () {
        it('creates a backup file', function () {
            $result = $this->service->create();

            expect($result)->toHaveKey('filename');
            expect($result)->toHaveKey('size');
            expect($result)->toHaveKey('manifest');
            
            expect(Storage::disk('backups')->exists($result['filename']))->toBeTrue();
        });

        it('includes manifest in backup', function () {
            $result = $this->service->create();

            expect($result['manifest'])->toHaveKey('version');
            expect($result['manifest'])->toHaveKey('app_version');
            expect($result['manifest'])->toHaveKey('created_at');
            expect($result['manifest'])->toHaveKey('contents');
        });

        it('can create database-only backup', function () {
            $result = $this->service->create([
                'include_database' => true,
                'include_files' => false,
                'include_settings' => false,
            ]);

            expect($result['manifest']['contents']['database'])->toBeTrue();
            expect($result['manifest']['contents'])->not->toHaveKey('files');
        });
    });

    describe('exists', function () {
        it('returns true for existing backup', function () {
            Storage::disk('backups')->put('test.zip', 'content');

            expect($this->service->exists('test.zip'))->toBeTrue();
        });

        it('returns false for non-existing backup', function () {
            expect($this->service->exists('nonexistent.zip'))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('deletes a backup file', function () {
            Storage::disk('backups')->put('test.zip', 'content');

            $this->service->delete('test.zip');

            expect(Storage::disk('backups')->exists('test.zip'))->toBeFalse();
        });
    });
});
