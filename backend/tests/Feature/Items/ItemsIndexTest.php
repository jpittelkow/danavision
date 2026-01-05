<?php

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;

test('authenticated users can view all items page', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    ListItem::factory()->count(3)->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/items');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 3)
            ->has('lists')
            ->has('filters')
    );
});

test('unauthenticated users cannot access items page', function () {
    $response = $this->get('/items');

    $response->assertRedirect('/login');
});

test('items page shows items from all user lists', function () {
    $user = User::factory()->create();
    
    $list1 = ShoppingList::factory()->create(['user_id' => $user->id]);
    $list2 = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list1->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Item from List 1',
    ]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list2->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Item from List 2',
    ]);

    $response = $this->actingAs($user)->get('/items');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 2)
    );
});

test('items page can filter by list', function () {
    $user = User::factory()->create();
    
    $list1 = ShoppingList::factory()->create(['user_id' => $user->id]);
    $list2 = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list1->id,
        'added_by_user_id' => $user->id,
    ]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list2->id,
        'added_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get("/items?list_id={$list1->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 1)
    );
});

test('items page can filter by price drop status', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    // Item with price drop
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 10.00,
        'previous_price' => 15.00,
    ]);
    
    // Item without price drop
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 20.00,
        'previous_price' => null,
    ]);

    $response = $this->actingAs($user)->get('/items?status=drops');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 1)
    );
});

test('items page can filter by all-time low status', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    // Item at all-time low
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 10.00,
        'lowest_price' => 10.00,
    ]);
    
    // Item not at all-time low
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 20.00,
        'lowest_price' => 15.00,
    ]);

    $response = $this->actingAs($user)->get('/items?status=all_time_lows');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 1)
    );
});

test('items page can filter by priority', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'priority' => 'high',
    ]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'priority' => 'low',
    ]);

    $response = $this->actingAs($user)->get('/items?priority=high');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 1)
    );
});

test('items page can sort by different fields', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Apple',
        'current_price' => 5.00,
    ]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Zebra',
        'current_price' => 100.00,
    ]);

    // Sort by name ascending
    $response = $this->actingAs($user)->get('/items?sort=product_name&dir=asc');
    $response->assertStatus(200);

    // Sort by price descending
    $response = $this->actingAs($user)->get('/items?sort=current_price&dir=desc');
    $response->assertStatus(200);
});

test('users cannot see items from other users lists', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    $list = ShoppingList::factory()->create(['user_id' => $user2->id]);
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user2->id,
    ]);

    $response = $this->actingAs($user1)->get('/items');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Items/Index')
            ->has('items.data', 0)
    );
});
