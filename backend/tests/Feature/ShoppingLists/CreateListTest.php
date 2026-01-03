<?php

use App\Models\User;
use App\Models\ShoppingList;

test('authenticated users can create shopping lists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/lists', [
        'name' => 'My Wishlist',
        'description' => 'Things I want to buy',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shopping_lists', [
        'name' => 'My Wishlist',
        'description' => 'Things I want to buy',
        'user_id' => $user->id,
    ]);
});

test('shopping list creation requires name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/lists', [
        'description' => 'Things I want to buy',
    ]);

    $response->assertSessionHasErrors('name');
});

test('unauthenticated users cannot create lists', function () {
    $response = $this->post('/lists', [
        'name' => 'My Wishlist',
    ]);

    $response->assertRedirect('/login');
});

test('users can view their lists', function () {
    $user = User::factory()->create();
    ShoppingList::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/lists');

    $response->assertStatus(200);
});

test('users can view a specific list', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/lists/{$list->id}");

    $response->assertStatus(200);
});
