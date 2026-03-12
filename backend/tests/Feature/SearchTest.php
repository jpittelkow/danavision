<?php

use App\Models\User;
use App\Services\Search\SearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

describe('Search (Scout / Meilisearch)', function () {
    it('configures User model as searchable', function () {
        $user = User::factory()->create([
            'name' => 'Searchable User',
            'email' => 'searchable@example.com',
        ]);

        expect($user->searchableAs())->toBe('users');

        $array = $user->toSearchableArray();
        expect($array)->toHaveKeys(['id', 'name', 'email', 'created_at'])
            ->and($array['name'])->toBe('Searchable User')
            ->and($array['email'])->toBe('searchable@example.com');
    });

    it('searchUsers returns paginated results', function () {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $service = app(SearchService::class);
        $results = $service->searchUsers('John');

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($results->total())->toBeGreaterThanOrEqual(1);
    });

    it('searchUsers returns ordered list for empty query', function () {
        User::factory()->create(['name' => 'Alpha User', 'email' => 'alpha@example.com']);
        User::factory()->create(['name' => 'Beta User', 'email' => 'beta@example.com']);

        $service = app(SearchService::class);
        $results = $service->searchUsers('');

        expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($results->total())->toBeGreaterThanOrEqual(2);
    });

    it('SearchService reindexAll returns results for each model', function () {
        User::factory()->count(2)->create();

        $service = app(SearchService::class);
        $results = $service->reindexAll();

        expect($results)->toHaveKey('users')
            ->and($results['users'])->toHaveKey('success')
            ->and($results['users']['success'])->toBeTrue();
    });

    it('search:reindex command runs successfully', function () {
        // Skip if using collection driver (in-memory) - reindex not supported
        if (config('scout.driver') === 'collection') {
            $this->markTestSkipped('Reindex command not supported with collection driver');
        }
        
        User::factory()->count(2)->create();

        $this->artisan('search:reindex')
            ->assertSuccessful();

        $this->artisan('search:reindex', ['model' => 'users'])
            ->assertSuccessful();
    });

    it('search:reindex command fails for unknown model', function () {
        $this->artisan('search:reindex', ['model' => 'invalid'])
            ->assertFailed();
    });

    it('search:reindex command accepts all searchable model names and pages', function () {
        // Skip if using collection driver (in-memory) - reindex not supported
        if (config('scout.driver') === 'collection') {
            $this->markTestSkipped('Reindex command not supported with collection driver');
        }
        
        $models = ['users', 'user_groups', 'notifications', 'email_templates', 'api_tokens', 'ai_providers', 'webhooks'];
        foreach ($models as $model) {
            $this->artisan('search:reindex', ['model' => $model])
                ->assertSuccessful();
        }
        $this->artisan('search:reindex', ['model' => 'pages'])
            ->assertSuccessful();
    });

    describe('Search API', function () {
        it('GET /search returns paginated results for authenticated admin', function () {
            User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
            $admin = createAdminUser();
            waitForSearch();

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/search?q=John');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [['id', 'type', 'title', 'subtitle', 'url', 'highlight']],
                    'meta' => ['query', 'total', 'page', 'per_page'],
                ])
                ->assertJsonPath('meta.query', 'John');
        });

        it('GET /search/suggestions returns limited results', function () {
            User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
            $admin = createAdminUser();
            waitForSearch();

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/search/suggestions?q=Jane&limit=3');

            $response->assertStatus(200)
                ->assertJsonStructure(['data' => [['id', 'type', 'title', 'subtitle', 'url']]]);
            expect(count($response->json('data')))->toBeLessThanOrEqual(3);
        });

        it('GET /search with type=users filters to users only', function () {
            User::factory()->create(['name' => 'Alice User', 'email' => 'alice@example.com']);
            $admin = createAdminUser();
            waitForSearch();

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/search?q=Alice&type=users');

            $response->assertStatus(200)
                ->assertJsonPath('data.0.type', 'user');
        });

        it('non-admin search is scoped to current user only', function () {
            $me = User::factory()->create(['name' => 'Only Me', 'email' => 'onlyme@example.com']);
            User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

            $response = $this->actingAs($me, 'sanctum')
                ->getJson('/api/search?q=User');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect($data)->toHaveCount(0);
        });

        it('non-admin finds self when query matches', function () {
            $me = User::factory()->create(['name' => 'My Name', 'email' => 'myname@example.com']);

            $response = $this->actingAs($me, 'sanctum')
                ->getJson('/api/search?q=My');

            $response->assertStatus(200)
                ->assertJsonPath('data.0.id', $me->id)
                ->assertJsonPath('meta.total', 1);
        });

        it('search requires authentication', function () {
            $response = $this->getJson('/api/search?q=test');
            $response->assertStatus(401);
        });

        it('GET /search with type=notifications returns only own for non-admin', function () {
            $me = User::factory()->create();
            $other = User::factory()->create();
            \App\Models\Notification::factory()->create(['user_id' => $me->id, 'title' => 'My notification', 'message' => 'Hello']);
            \App\Models\Notification::factory()->create(['user_id' => $other->id, 'title' => 'Other notification', 'message' => 'Other']);

            $response = $this->actingAs($me, 'sanctum')
                ->getJson('/api/search?q=notification&type=notifications');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect($data)->toHaveCount(1);
            expect($data[0]['title'])->toContain('My');
        });

        it('GET /search with type=email_templates returns empty for non-admin', function () {
            $user = User::factory()->create();
            \App\Models\EmailTemplate::create([
                'key' => 'search_test_template',
                'name' => 'Test template',
                'subject' => 'Test',
                'body_html' => '<p>Test</p>',
                'body_text' => 'Test',
                'is_system' => false,
                'is_active' => true,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/search?q=Test&type=email_templates');

            $response->assertStatus(200)
                ->assertJsonPath('meta.total', 0)
                ->assertJsonPath('data', []);
        });

        it('GET /search with type=email_templates returns results for admin', function () {
            \App\Models\EmailTemplate::create([
                'key' => 'search_welcome_template',
                'name' => 'Welcome email',
                'subject' => 'Welcome',
                'body_html' => '<p>Hi</p>',
                'body_text' => 'Hi',
                'is_system' => false,
                'is_active' => true,
            ]);
            $admin = createAdminUser();

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/search?q=Welcome&type=email_templates');

            $response->assertStatus(200);
            $data = $response->json('data');
            expect($data)->not->toHaveCount(0);
            expect($data[0]['type'])->toBe('email_template');
        });
    });

    describe('Search Admin API', function () {
        it('GET /admin/search/stats returns index stats for admin', function () {
            $admin = createAdminUser();

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/search/stats');

            $response->assertStatus(200)
                ->assertJsonStructure(['stats' => ['users' => ['count', 'name']]])
                ->assertJsonPath('stats.users.name', 'users');
            $stats = $response->json('stats');
            expect($stats)->toHaveKeys(['users', 'notifications', 'email_templates', 'api_tokens', 'ai_providers', 'webhooks']);
        });

        it('POST /admin/search/reindex reindexes all when no model', function () {
            // Skip if using collection driver (in-memory) - reindex not supported
            if (config('scout.driver') === 'collection') {
                $this->markTestSkipped('Reindex API not supported with collection driver');
            }
            
            User::factory()->count(2)->create();
            $admin = createAdminUser();

            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/admin/search/reindex');

            $response->assertStatus(200)
                ->assertJsonFragment(['message' => 'All indexes reindexed successfully.']);
        });

        it('POST /admin/search/reindex reindexes single model', function () {
            // Skip if using collection driver (in-memory) - reindex not supported
            if (config('scout.driver') === 'collection') {
                $this->markTestSkipped('Reindex API not supported with collection driver');
            }
            
            User::factory()->create();
            $admin = createAdminUser();

            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/admin/search/reindex', ['model' => 'users']);

            $response->assertStatus(200)
                ->assertJsonFragment(['message' => 'Index reindexed successfully.']);
        });

        it('admin search stats requires admin', function () {
            $user = User::factory()->create();
            $response = $this->actingAs($user, 'sanctum')->getJson('/api/admin/search/stats');
            $response->assertStatus(403);
        });
    });
});
