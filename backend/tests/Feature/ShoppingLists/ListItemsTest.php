<?php

use App\Models\User;
use App\Models\ShoppingList;
use App\Models\ListItem;

test('users can add items to their lists', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/lists/{$list->id}/items", [
        'product_name' => 'Sony WH-1000XM5',
        'target_price' => 299.99,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('list_items', [
        'product_name' => 'Sony WH-1000XM5',
        'shopping_list_id' => $list->id,
    ]);
});

test('users cannot add items to lists they do not own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->post("/lists/{$list->id}/items", [
        'product_name' => 'Test Product',
    ]);

    $response->assertStatus(403);
});

test('users can mark items as purchased', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    $item = ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'is_purchased' => false,
    ]);

    $response = $this->actingAs($user)->post("/items/{$item->id}/purchased", [
        'purchased_price' => 45.00,
    ]);

    $response->assertRedirect();
    $this->assertTrue($item->fresh()->is_purchased);
});

test('users can delete items from their lists', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    $item = ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete("/items/{$item->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
});
