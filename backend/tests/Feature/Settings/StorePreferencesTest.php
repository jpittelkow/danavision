<?php

use App\Models\Store;
use App\Models\User;
use App\Models\UserStorePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed some default stores for testing
    Store::create([
        'name' => 'Amazon',
        'slug' => 'amazon',
        'domain' => 'amazon.com',
        'search_url_template' => 'https://www.amazon.com/s?k={query}',
        'is_default' => true,
        'is_active' => true,
        'category' => 'general',
        'default_priority' => 100,
    ]);

    Store::create([
        'name' => 'Walmart',
        'slug' => 'walmart',
        'domain' => 'walmart.com',
        'search_url_template' => 'https://www.walmart.com/search?q={query}',
        'is_default' => true,
        'is_active' => true,
        'category' => 'general',
        'default_priority' => 95,
    ]);

    Store::create([
        'name' => 'Target',
        'slug' => 'target',
        'domain' => 'target.com',
        'search_url_template' => 'https://www.target.com/s?searchTerm={query}',
        'is_default' => true,
        'is_active' => true,
        'category' => 'general',
        'default_priority' => 90,
    ]);
});

test('user can view stores on settings page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('stores')
        ->has('storeCategories')
    );
});

test('user can get stores via api', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/api/stores');

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);
    $response->assertJsonCount(3, 'stores');
});

test('user can update store preference', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'amazon')->first();

    $response = $this->actingAs($user)->patch("/api/stores/{$store->id}/preference", [
        'enabled' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'preference' => [
            'enabled' => false,
        ],
    ]);

    // Verify in database
    $this->assertDatabaseHas('user_store_preferences', [
        'user_id' => $user->id,
        'store_id' => $store->id,
        'enabled' => false,
    ]);
});

test('user can toggle store favorite', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'walmart')->first();

    // First toggle - should become favorite
    $response = $this->actingAs($user)->post("/api/stores/{$store->id}/favorite");

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'is_favorite' => true,
    ]);

    // Second toggle - should remove favorite
    $response = $this->actingAs($user)->post("/api/stores/{$store->id}/favorite");

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'is_favorite' => false,
    ]);
});

test('user can toggle store local status', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'walmart')->first();

    // Ensure store starts as not local
    $store->update(['is_local' => false]);

    // First toggle - should become local
    $response = $this->actingAs($user)->post("/api/stores/{$store->id}/local");

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'is_local' => true,
    ]);

    // Verify database was updated
    expect($store->fresh()->is_local)->toBeTrue();

    // Second toggle - should remove local
    $response = $this->actingAs($user)->post("/api/stores/{$store->id}/local");

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'is_local' => false,
    ]);

    // Verify database was updated
    expect($store->fresh()->is_local)->toBeFalse();
});

test('toggle local returns 404 for non-existent store', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/stores/99999/local');

    $response->assertStatus(404);
    $response->assertJson([
        'success' => false,
        'message' => 'Store not found',
    ]);
});

test('user can update store priorities', function () {
    $user = User::factory()->create();
    $amazon = Store::where('slug', 'amazon')->first();
    $walmart = Store::where('slug', 'walmart')->first();
    $target = Store::where('slug', 'target')->first();

    $response = $this->actingAs($user)->patch('/api/stores/priorities', [
        'store_order' => [$target->id, $walmart->id, $amazon->id],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);

    // Verify priorities
    $targetPref = UserStorePreference::where('user_id', $user->id)
        ->where('store_id', $target->id)
        ->first();
    $amazonPref = UserStorePreference::where('user_id', $user->id)
        ->where('store_id', $amazon->id)
        ->first();

    expect($targetPref->priority)->toBeGreaterThan($amazonPref->priority);
});

test('user can add custom store', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/api/stores', [
        'name' => 'My Local Shop',
        'domain' => 'mylocalshop.com',
        'category' => 'specialty',
        'is_local' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'store' => [
            'name' => 'My Local Shop',
            'domain' => 'mylocalshop.com',
            'is_new' => true,
        ],
    ]);

    // Verify store was created
    $this->assertDatabaseHas('stores', [
        'name' => 'My Local Shop',
        'domain' => 'mylocalshop.com',
        'is_local' => true,
    ]);

    // Verify user preference was created (enabled and favorite)
    $store = Store::where('domain', 'mylocalshop.com')->first();
    $this->assertDatabaseHas('user_store_preferences', [
        'user_id' => $user->id,
        'store_id' => $store->id,
        'enabled' => true,
        'is_favorite' => true,
    ]);
});

test('adding existing store domain enables it for user', function () {
    $user = User::factory()->create();
    $amazon = Store::where('slug', 'amazon')->first();

    // Try to add Amazon again
    $response = $this->actingAs($user)->post('/api/stores', [
        'name' => 'Amazon Store',
        'domain' => 'www.amazon.com',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'store' => [
            'id' => $amazon->id,
            'is_new' => false,
        ],
    ]);

    // Verify preference was created
    $this->assertDatabaseHas('user_store_preferences', [
        'user_id' => $user->id,
        'store_id' => $amazon->id,
        'enabled' => true,
        'is_favorite' => true,
    ]);
});

test('user can reset store preferences', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'amazon')->first();

    // Create some preferences
    UserStorePreference::create([
        'user_id' => $user->id,
        'store_id' => $store->id,
        'enabled' => false,
        'is_favorite' => true,
        'priority' => 200,
    ]);

    $response = $this->actingAs($user)->post('/api/stores/reset');

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);

    // Verify preferences were deleted
    $this->assertDatabaseMissing('user_store_preferences', [
        'user_id' => $user->id,
    ]);
});

test('store preference update validates input', function () {
    $user = User::factory()->create();
    $store = Store::where('slug', 'amazon')->first();

    // Invalid priority - use patchJson to get JSON validation errors instead of redirect
    $response = $this->actingAs($user)->patchJson("/api/stores/{$store->id}/preference", [
        'priority' => 9999, // Over max
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['priority']);
});

test('cannot update preference for non-existent store', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson('/api/stores/99999/preference', [
        'enabled' => false,
    ]);

    $response->assertStatus(404);
    $response->assertJson(['success' => false]);
});

test('unauthenticated user cannot access store endpoints', function () {
    $store = Store::where('slug', 'amazon')->first();

    $this->get('/api/stores')->assertStatus(302);
    $this->patch("/api/stores/{$store->id}/preference", [])->assertStatus(302);
    $this->post('/api/stores', [])->assertStatus(302);
});
