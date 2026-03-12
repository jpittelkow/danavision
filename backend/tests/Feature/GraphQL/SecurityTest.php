<?php

use App\Services\SettingService;

beforeEach(function () {
    app(SettingService::class)->set('graphql', 'enabled', true);
});

describe('introspection', function () {
    it('is disabled by default', function () {
        config(['lighthouse.security.disable_introspection' => \GraphQL\Validator\Rules\DisableIntrospection::ENABLED]);

        $user = createUser();
        $key = createApiKey($user);

        $response = graphQL(
            '{ __schema { types { name } } }',
            [],
            $key['plaintext']
        )->assertStatus(200);

        // When introspection is disabled Lighthouse returns an error
        expect($response->json('errors'))->not->toBeNull();
    });

    it('is available when enabled', function () {
        config([
            'lighthouse.security.disable_introspection' => \GraphQL\Validator\Rules\DisableIntrospection::DISABLED,
        ]);

        $user = createUser();
        $key = createApiKey($user);

        $response = graphQL(
            '{ __schema { queryType { name } } }',
            [],
            $key['plaintext']
        )->assertStatus(200);

        expect($response->json('data.__schema.queryType.name'))->toBe('Query');
    });
});

describe('authorization', function () {
    it('prevents non-admin access to protected queries', function () {
        $user = createUser();
        $key = createApiKey($user);

        // auditLogs requires admin permission
        $response = graphQL(
            '{ auditLogs(first: 1) { data { user { id } } } }',
            [],
            $key['plaintext']
        )->assertStatus(200);

        // Non-admin should get FORBIDDEN error
        expect($response->json('errors'))->not->toBeNull();
        expect($response->json('errors.0.message'))->toContain('This action is unauthorized');
    });
});

describe('max result size', function () {
    it('clamps first parameter to max_result_size', function () {
        config(['graphql.max_result_size' => 5]);

        $user = createUser();
        $key = createApiKey($user);

        // Create 10 notifications
        \App\Models\Notification::factory()->count(10)->create(['user_id' => $user->id]);

        $response = graphQL(
            '{ myNotifications(first: 100, page: 1) { data { id } paginatorInfo { perPage } } }',
            [],
            $key['plaintext']
        )->assertStatus(200);

        // perPage should be clamped to 5
        expect($response->json('data.myNotifications.paginatorInfo.perPage'))->toBeLessThanOrEqual(5);
    });
});

describe('rate limiting', function () {
    it('returns rate limit headers', function () {
        config(['graphql.default_rate_limit' => 60]);

        $user = createUser();
        $key = createApiKey($user);

        $response = graphQL(
            '{ me { id } }',
            [],
            $key['plaintext']
        )->assertStatus(200);

        expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
        expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
    });
});
