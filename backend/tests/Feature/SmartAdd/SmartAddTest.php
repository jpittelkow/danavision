<?php

use App\Models\User;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
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

test('users can add item to list from smart add', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'product_url' => 'https://example.com/product',
        'current_price' => 99.99,
        'current_retailer' => 'Amazon',
        'priority' => 'medium',
    ]);

    $response->assertRedirect("/lists/{$list->id}");
    $this->assertDatabaseHas('list_items', [
        'product_name' => 'Test Product',
        'shopping_list_id' => $list->id,
        'current_price' => 99.99,
    ]);
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

test('smart add sets initial lowest and highest price from current price', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/smart-add/add', [
        'list_id' => $list->id,
        'product_name' => 'Test Product',
        'current_price' => 149.99,
        'priority' => 'medium',
    ]);

    $response->assertRedirect();
    
    $item = \App\Models\ListItem::where('product_name', 'Test Product')->first();
    expect($item->lowest_price)->toBe('149.99');
    expect($item->highest_price)->toBe('149.99');
});
