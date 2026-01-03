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

// Generic Item Tests

test('users can add generic items to their lists', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/lists/{$list->id}/items", [
        'product_name' => 'Blueberries',
        'is_generic' => true,
        'unit_of_measure' => 'lb',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('list_items', [
        'product_name' => 'Blueberries',
        'shopping_list_id' => $list->id,
        'is_generic' => true,
        'unit_of_measure' => 'lb',
    ]);
});

test('users can add specific items with SKU', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/lists/{$list->id}/items", [
        'product_name' => 'Sony WH-1000XM5',
        'is_generic' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('list_items', [
        'product_name' => 'Sony WH-1000XM5',
        'shopping_list_id' => $list->id,
        'is_generic' => false,
    ]);
});

test('users can update item to be generic', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    $item = ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'is_generic' => false,
        'unit_of_measure' => null,
    ]);

    $response = $this->actingAs($user)->patch("/items/{$item->id}", [
        'is_generic' => true,
        'unit_of_measure' => 'gallon',
    ]);

    $response->assertRedirect();
    $item->refresh();
    expect($item->is_generic)->toBeTrue();
    expect($item->unit_of_measure)->toBe('gallon');
});

test('users can update generic item unit of measure', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    $item = ListItem::factory()->generic('lb')->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->patch("/items/{$item->id}", [
        'unit_of_measure' => 'oz',
    ]);

    $response->assertRedirect();
    $item->refresh();
    expect($item->unit_of_measure)->toBe('oz');
});

test('generic items can be created with factory state', function () {
    $item = ListItem::factory()->generic('gallon')->create();

    expect($item->is_generic)->toBeTrue();
    expect($item->unit_of_measure)->toBe('gallon');
    expect($item->sku)->toBeNull();
});

test('list item model formats price correctly for generic items', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);
    
    $genericItem = ListItem::factory()->generic('lb')->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 4.99,
    ]);

    $specificItem = ListItem::factory()->create([
        'shopping_list_id' => $list->id,
        'added_by_user_id' => $user->id,
        'current_price' => 299.99,
        'is_generic' => false,
    ]);

    expect($genericItem->getFormattedPrice())->toBe('$4.99/lb');
    expect($specificItem->getFormattedPrice())->toBe('$299.99');
});

test('list item isGeneric method returns correct value', function () {
    $genericItem = ListItem::factory()->generic()->make();
    $specificItem = ListItem::factory()->make(['is_generic' => false]);

    expect($genericItem->isGeneric())->toBeTrue();
    expect($specificItem->isGeneric())->toBeFalse();
});
