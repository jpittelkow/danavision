<?php

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\ShoppingList;
use App\Models\User;

test('authenticated users can access the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('stats')
            ->has('recent_drops')
            ->has('all_time_lows')
            ->has('store_stats')
            ->has('active_jobs')
            ->has('price_trend')
            ->has('items_needing_attention')
    );
});

test('dashboard shows correct item counts', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    ListItem::factory()->count(5)->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->where('stats.lists_count', 1)
            ->where('stats.items_count', 5)
    );
});

test('dashboard shows recent price drops', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    // Item with price drop
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Test Item',
        'current_price' => 10.00,
        'previous_price' => 15.00,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('recent_drops', 1)
            ->where('stats.items_with_drops', 1)
    );
});

test('dashboard shows all-time lows', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    // Item at all-time low
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Best Price Item',
        'current_price' => 10.00,
        'lowest_price' => 10.00,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('all_time_lows', 1)
            ->where('stats.all_time_lows_count', 1)
    );
});

test('dashboard calculates potential savings correctly', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    // Two items with price drops
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 10.00,
        'previous_price' => 15.00, // $5 savings
    ]);
    
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 20.00,
        'previous_price' => 30.00, // $10 savings
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->where('stats.total_potential_savings', 15)
    );
});

test('dashboard shows active jobs count', function () {
    $user = User::factory()->create();
    
    // Create an active job
    AIJob::create([
        'user_id' => $user->id,
        'type' => 'price_search',
        'status' => 'processing',
        'input_data' => ['product_name' => 'Test'],
        'progress' => 50,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->where('active_jobs_count', 1)
            ->has('active_jobs', 1)
    );
});

test('dashboard shows store leaderboard', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    $item = ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
    ]);
    
    // Add vendor prices
    ItemVendorPrice::create([
        'list_item_id' => $item->id,
        'vendor' => 'Amazon',
        'current_price' => 10.00,
        'in_stock' => true,
    ]);
    
    ItemVendorPrice::create([
        'list_item_id' => $item->id,
        'vendor' => 'Walmart',
        'current_price' => 12.00,
        'in_stock' => true,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('store_stats')
    );
});

test('dashboard shows items needing attention', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    // Item not checked in over 7 days
    ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'product_name' => 'Old Item',
        'is_purchased' => false,
        'last_checked_at' => now()->subDays(10),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('items_needing_attention', 1)
    );
});

test('dashboard provides 7-day price trend data', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    $item = ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
    ]);
    
    // Add price history
    PriceHistory::create([
        'list_item_id' => $item->id,
        'price' => 15.00,
        'retailer' => 'Amazon',
        'captured_at' => now()->subDays(3),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('price_trend', 7)
    );
});

test('dashboard does not show other users data', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    // User2's data
    $list = ShoppingList::factory()->create(['user_id' => $user2->id]);
    ListItem::factory()->count(5)->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user2->id,
    ]);

    // User1 should not see User2's items
    $response = $this->actingAs($user1)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->where('stats.items_count', 0)
    );
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('root path redirects to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});
