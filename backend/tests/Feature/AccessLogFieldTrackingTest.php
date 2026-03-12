<?php

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;

describe('Access log field tracking', function () {
    beforeEach(function () {
        Config::set('logging.hipaa_access_logging_enabled', true);
    });

    it('logs fields_accessed for profile view', function () {
        $admin = createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/profile')
            ->assertStatus(200);

        $log = AccessLog::where('resource_type', 'User')
            ->where('action', 'view')
            ->where('user_id', $admin->id)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->fields_accessed)->not->toBeNull()
            ->and($log->fields_accessed)->toBeArray()
            ->and($log->fields_accessed)->not->toBeEmpty();
    });

    it('logs fields_accessed for user settings view', function () {
        $admin = createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/user/settings')
            ->assertStatus(200);

        $log = AccessLog::where('resource_type', 'Setting')
            ->where('action', 'view')
            ->where('user_id', $admin->id)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->fields_accessed)->not->toBeNull()
            ->and($log->fields_accessed)->toBeArray()
            ->and($log->fields_accessed)->not->toBeEmpty();
    });

    it('logs fields_accessed for user settings update', function () {
        $admin = createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/user/settings', [
                'theme' => 'dark',
                'default_llm_mode' => 'single',
            ])
            ->assertStatus(200);

        $log = AccessLog::where('resource_type', 'Setting')
            ->where('action', 'update')
            ->where('user_id', $admin->id)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->fields_accessed)->not->toBeNull()
            ->and($log->fields_accessed)->toBeArray()
            ->and($log->fields_accessed)->not->toBeEmpty();
    });

    it('excludes sensitive keys from fields_accessed', function () {
        $admin = createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/user/settings', [
                'theme' => 'light',
                'password' => 'secret123',
            ])
            ->assertSuccessful();

        $log = AccessLog::where('resource_type', 'Setting')
            ->where('action', 'update')
            ->where('user_id', $admin->id)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->fields_accessed)->not->toBeNull();
        expect(collect($log->fields_accessed)->contains(fn ($k) => stripos($k, 'password') !== false))->toBeFalse();
    });

    it('returns null fields_accessed when all keys are sensitive', function () {
        $admin = createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/user/settings', [
                '_token' => 'fake',
                'password' => 'secret123',
            ])
            ->assertSuccessful();

        $log = AccessLog::where('resource_type', 'Setting')
            ->where('action', 'update')
            ->where('user_id', $admin->id)
            ->latest()
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->fields_accessed)->toBeNull();
    });

    it('access-logs API returns fields_accessed for UI display', function () {
        $admin = createAdminUser();
        $this->actingAs($admin, 'sanctum')->getJson('/api/profile')->assertStatus(200);

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/access-logs?per_page=5')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);

        $data = $res->json('data');
        expect($data)->not->toBeEmpty();
        $latest = $data[0];
        expect($latest)->toHaveKeys(['action', 'resource_type', 'fields_accessed'])
            ->and($latest['fields_accessed'])->toBeArray()
            ->and($latest['fields_accessed'])->not->toBeEmpty();
    });
});
