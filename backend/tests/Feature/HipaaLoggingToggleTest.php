<?php

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;

describe('HIPAA access logging toggle', function () {
    beforeEach(function () {
        Config::set('logging.hipaa_access_logging_enabled', true);
    });

    it('log retention GET returns hipaa_access_logging_enabled', function () {
        $admin = createAdminUser();

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/log-retention')
            ->assertStatus(200);

        expect($res->json('settings'))->toHaveKey('hipaa_access_logging_enabled')
            ->and($res->json('settings.hipaa_access_logging_enabled'))->toBeIn([true, false]);
    });

    it('log retention PUT accepts and saves hipaa_access_logging_enabled', function () {
        $admin = createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/log-retention', ['hipaa_access_logging_enabled' => false])
            ->assertStatus(200);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/log-retention')->assertStatus(200);
        expect($res->json('settings.hipaa_access_logging_enabled'))->toBeFalse();
    });

    it('DELETE access-logs returns 422 when HIPAA logging enabled', function () {
        $admin = createAdminUser();
        Config::set('logging.hipaa_access_logging_enabled', true);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/access-logs')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'HIPAA access logging is enabled. Disable it in Log retention settings to delete all access logs.']);
    });

    it('DELETE access-logs deletes all when HIPAA logging disabled', function () {
        $admin = createAdminUser();
        AccessLog::create([
            'user_id' => $admin->id,
            'action' => 'view',
            'resource_type' => 'User',
            'resource_id' => $admin->id,
            'ip_address' => '127.0.0.1',
        ]);
        expect(AccessLog::count())->toBe(1);

        Config::set('logging.hipaa_access_logging_enabled', false);

        $res = $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/access-logs')
            ->assertStatus(200);

        expect(AccessLog::count())->toBe(0)
            ->and($res->json('deleted_count'))->toBe(1);
    });

    it('does not create access logs when HIPAA logging disabled', function () {
        $admin = createAdminUser();
        Config::set('logging.hipaa_access_logging_enabled', false);

        $this->actingAs($admin, 'sanctum')->getJson('/api/profile')->assertStatus(200);

        expect(AccessLog::count())->toBe(0);
    });
});
