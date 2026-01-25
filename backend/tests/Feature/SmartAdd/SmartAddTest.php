<?php

use App\Models\AIProvider;
use App\Models\User;
use App\Models\Setting;
use App\Models\ShoppingList;
use App\Models\SmartAddQueueItem;
use App\Models\ListItem;
use App\Jobs\AI\FirecrawlDiscoveryJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

/**
 * Helper to set up a user with AI provider configured for Crawl4AI.
 * The StoreDiscoveryService requires both Crawl4AI running and an AI provider.
 */
function setupUserWithCrawl4AI(): User
{
    $user = User::factory()->create();
    
    // Create an AI provider for the user (required for price extraction)
    AIProvider::create([
        'user_id' => $user->id,
        'provider' => AIProvider::PROVIDER_OPENAI,
        'api_key' => encrypt('test-api-key'),
        'model' => 'gpt-4o-mini',
        'is_active' => true,
        'is_primary' => true,
    ]);

    // Mock Crawl4AI health check
    Http::fake([
        '127.0.0.1:5000/health' => Http::response(['status' => 'ok'], 200),
    ]);
    
    return $user;
}

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

test('users can add item to list from smart add and are redirected to item page', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'product_url' => 'https://example.com/product',
        'priority' => 'medium',
    ]);

    $this->assertDatabaseHas('list_items', [
        'product_name' => 'Test Product',
        'shopping_list_id' => $list->id,
    ]);
    
    // Should redirect to the item page (not the list page)
    $item = ListItem::where('product_name', 'Test Product')->first();
    $response->assertRedirect("/items/{$item->id}");
});

test('adding item dispatches background price search job', function () {
    // Use helper that sets up AI provider and mocks Crawl4AI health check
    $user = setupUserWithCrawl4AI();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'priority' => 'medium',
    ]);

    $item = ListItem::where('product_name', 'Test Product')->first();
    $response->assertRedirect("/items/{$item->id}");

    // Verify the FirecrawlDiscoveryJob was dispatched (uses Crawl4AI backend)
    Queue::assertPushed(FirecrawlDiscoveryJob::class);
});

test('adding item can skip price search job', function () {
    // Use helper that sets up AI provider and mocks Crawl4AI health check
    $user = setupUserWithCrawl4AI();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product Skip',
        'priority' => 'medium',
        'skip_price_search' => true,
    ]);

    $item = ListItem::where('product_name', 'Test Product Skip')->first();
    $response->assertRedirect("/items/{$item->id}");

    // Verify NO job was dispatched when skip_price_search is true
    Queue::assertNotPushed(FirecrawlDiscoveryJob::class);
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

    // Check that item was created with uploaded image path
    $item = ListItem::where('product_name', 'Product With Image')->first();
    expect($item)->not->toBeNull();
    expect($item->uploaded_image_path)->not->toBeNull();
    
    $response->assertRedirect("/items/{$item->id}");
    
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

    $item = ListItem::where('product_name', 'Sony Headphones')->first();
    expect($item->upc)->toBe('027242917576');
    $response->assertRedirect("/items/{$item->id}");
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

    $item = ListItem::where('product_name', 'Organic Bananas')->first();
    expect($item->is_generic)->toBe(true);
    expect($item->unit_of_measure)->toBe('lb');
    $response->assertRedirect("/items/{$item->id}");
});

// ==========================================
// Smart Add Queue Tests
// ==========================================

test('smart add page includes queue data', function () {
    $user = User::factory()->create();
    
    // Create some queue items
    SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Test Product', 'brand' => 'Test', 'confidence' => 90],
        ],
        'source_type' => 'text',
        'source_query' => 'test query',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($user)->get('/smart-add');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('SmartAdd')
        ->has('queue', 1)
        ->where('queueCount', 1)
    );
});

test('queue items from other users are not included', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    // Create queue item for other user
    SmartAddQueueItem::create([
        'user_id' => $otherUser->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Other Product', 'confidence' => 90],
        ],
        'source_type' => 'text',
        'source_query' => 'other query',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($user)->get('/smart-add');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('queue', 0)
        ->where('queueCount', 0)
    );
});

test('expired queue items are not included', function () {
    $user = User::factory()->create();
    
    // Create expired queue item
    SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Expired Product', 'confidence' => 90],
        ],
        'source_type' => 'text',
        'source_query' => 'expired query',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->get('/smart-add');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('queue', 0)
        ->where('queueCount', 0)
    );
});

test('user can dismiss queue item', function () {
    $user = User::factory()->create();
    
    $queueItem = SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Test Product', 'confidence' => 90],
        ],
        'source_type' => 'text',
        'source_query' => 'test',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($user)->deleteJson("/smart-add/queue/{$queueItem->id}");

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    
    $queueItem->refresh();
    expect($queueItem->status)->toBe(SmartAddQueueItem::STATUS_DISMISSED);
});

test('user cannot dismiss other users queue items', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    $queueItem = SmartAddQueueItem::create([
        'user_id' => $otherUser->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Test Product', 'confidence' => 90],
        ],
        'source_type' => 'text',
        'source_query' => 'test',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($user)->deleteJson("/smart-add/queue/{$queueItem->id}");

    $response->assertStatus(403);
    
    $queueItem->refresh();
    expect($queueItem->status)->toBe(SmartAddQueueItem::STATUS_PENDING);
});

test('user can add queue item to list', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    $queueItem = SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Queue Test Product', 'brand' => 'Test Brand', 'upc' => '123456789012', 'confidence' => 95],
            ['product_name' => 'Queue Test Product Alt', 'brand' => 'Test Brand', 'confidence' => 80],
        ],
        'source_type' => 'text',
        'source_query' => 'test query',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($user)->postJson("/smart-add/queue/{$queueItem->id}/add", [
        'list_id' => $list->id,
        'selected_index' => 0,
        'priority' => 'high',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    
    // Check item was created
    $item = ListItem::where('product_name', 'Queue Test Product')->first();
    expect($item)->not->toBeNull();
    expect($item->shopping_list_id)->toBe($list->id);
    expect($item->upc)->toBe('123456789012');
    
    // Check queue item was updated
    $queueItem->refresh();
    expect($queueItem->status)->toBe(SmartAddQueueItem::STATUS_ADDED);
    expect($queueItem->added_item_id)->toBe($item->id);
    expect($queueItem->selected_index)->toBe(0);
});

test('adding queue item to list dispatches price search', function () {
    // Use helper that sets up AI provider and mocks Crawl4AI health check
    $user = setupUserWithCrawl4AI();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    $queueItem = SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [
            ['product_name' => 'Queue Price Test', 'confidence' => 90],
        ],
        'source_type' => 'text',
        'source_query' => 'test',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($user)->postJson("/smart-add/queue/{$queueItem->id}/add", [
        'list_id' => $list->id,
        'selected_index' => 0,
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(FirecrawlDiscoveryJob::class);
});

test('queue item model creates from job results', function () {
    $user = User::factory()->create();
    
    $productData = [
        ['product_name' => 'Test Product', 'brand' => 'Test', 'confidence' => 95],
        ['product_name' => 'Test Product Alt', 'confidence' => 80],
    ];
    
    $queueItem = SmartAddQueueItem::createFromJobResults(
        userId: $user->id,
        productData: $productData,
        sourceType: SmartAddQueueItem::SOURCE_TEXT,
        sourceQuery: 'test query',
        providersUsed: ['claude', 'openai']
    );
    
    expect($queueItem)->toBeInstanceOf(SmartAddQueueItem::class);
    expect($queueItem->user_id)->toBe($user->id);
    expect($queueItem->status)->toBe(SmartAddQueueItem::STATUS_PENDING);
    expect($queueItem->product_data)->toHaveCount(2);
    expect($queueItem->source_type)->toBe('text');
    expect($queueItem->source_query)->toBe('test query');
    expect($queueItem->providers_used)->toBe(['claude', 'openai']);
    expect($queueItem->expires_at)->not->toBeNull();
});

test('queue item cleanup removes expired items', function () {
    $user = User::factory()->create();
    
    // Create expired item
    $expired = SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [['product_name' => 'Expired']],
        'source_type' => 'text',
        'expires_at' => now()->subDay(),
    ]);
    
    // Create valid item
    $valid = SmartAddQueueItem::create([
        'user_id' => $user->id,
        'status' => SmartAddQueueItem::STATUS_PENDING,
        'product_data' => [['product_name' => 'Valid']],
        'source_type' => 'text',
        'expires_at' => now()->addDays(7),
    ]);
    
    $deleted = SmartAddQueueItem::cleanupExpired();
    
    expect($deleted)->toBe(1);
    expect(SmartAddQueueItem::find($expired->id))->toBeNull();
    expect(SmartAddQueueItem::find($valid->id))->not->toBeNull();
});
