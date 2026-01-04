<?php

use App\Models\User;
use App\Models\ShoppingList;
use App\Jobs\SearchItemPrices;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

test('smart add page is accessible when authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/smart-add');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('SmartAdd'));
});

test('smart add page requires authentication', function () {
    $response = $this->get('/smart-add');

    $response->assertRedirect('/login');
});

test('identify endpoint returns json response for text query', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/smart-add/identify', [
        'query' => 'Sony WH-1000XM5 headphones',
    ]);

    // Should return JSON with expected structure
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'results',
        'providers_used',
        'error',
    ]);
});

test('identify endpoint requires image or query', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/smart-add/identify', [
        // neither image nor query provided
    ]);

    $response->assertStatus(422);
    $response->assertJson(['error' => 'Please provide an image or search query.']);
});

test('identify endpoint requires authentication', function () {
    $response = $this->postJson('/smart-add/identify', [
        'query' => 'Test Product',
    ]);

    $response->assertStatus(401);
});

test('users can add item to list from smart add', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'product_url' => 'https://example.com/product',
        'priority' => 'medium',
    ]);

    $response->assertRedirect("/lists/{$list->id}");
    $this->assertDatabaseHas('list_items', [
        'product_name' => 'Test Product',
        'shopping_list_id' => $list->id,
    ]);
});

test('adding item dispatches background price search job', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'priority' => 'medium',
    ]);

    $response->assertRedirect("/lists/{$list->id}");

    // Verify the SearchItemPrices job was dispatched
    Queue::assertPushed(SearchItemPrices::class, function ($job) use ($user) {
        return $job->userId === $user->id;
    });
});

test('adding item can skip price search job', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'priority' => 'medium',
        'skip_price_search' => true,
    ]);

    $response->assertRedirect("/lists/{$list->id}");

    // Verify NO job was dispatched
    Queue::assertNotPushed(SearchItemPrices::class);
});

test('users can add item with uploaded image from smart add', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    // Create a base64 encoded test image
    $base64Image = 'data:image/jpeg;base64,' . base64_encode('fake jpeg content');

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Product With Image',
        'uploaded_image' => $base64Image,
        'priority' => 'medium',
    ]);

    $response->assertRedirect("/lists/{$list->id}");
    
    // Check that item was created with uploaded image path
    $item = \App\Models\ListItem::where('product_name', 'Product With Image')->first();
    expect($item)->not->toBeNull();
    expect($item->uploaded_image_path)->not->toBeNull();
    
    // Check that file was stored
    Storage::disk('public')->assertExists($item->uploaded_image_path);
});

test('users cannot add items to lists they do not own via smart add', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
    ]);

    $response->assertStatus(403);
});

test('smart add validates product name is required', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        // missing product_name
    ]);

    $response->assertSessionHasErrors('product_name');
});

test('smart add validates list_id exists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => 99999,
        'product_name' => 'Test',
    ]);

    $response->assertSessionHasErrors('list_id');
});

test('smart add includes upc when adding item', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Sony Headphones',
        'upc' => '027242917576',
        'priority' => 'medium',
    ]);

    $response->assertRedirect("/lists/{$list->id}");
    
    $item = \App\Models\ListItem::where('product_name', 'Sony Headphones')->first();
    expect($item->upc)->toBe('027242917576');
});

test('smart add handles generic items with unit of measure', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Organic Bananas',
        'is_generic' => true,
        'unit_of_measure' => 'lb',
        'priority' => 'medium',
    ]);

    $response->assertRedirect("/lists/{$list->id}");
    
    $item = \App\Models\ListItem::where('product_name', 'Organic Bananas')->first();
    expect($item->is_generic)->toBe(true);
    expect($item->unit_of_measure)->toBe('lb');
});
