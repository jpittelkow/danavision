<?php

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->list = ShoppingList::factory()->create([
        'user_id' => $this->user->id,
    ]);
});

it('stores upc when creating a list item', function () {
    $this->actingAs($this->user);

    $response = $this->post("/lists/{$this->list->id}/items", [
        'product_name' => 'Test Product',
        'upc' => '012345678901',
    ]);

    $response->assertRedirect();
    
    $item = ListItem::where('shopping_list_id', $this->list->id)->first();
    expect($item)->not->toBeNull();
    expect($item->upc)->toBe('012345678901');
});

it('updates upc on existing item', function () {
    $this->actingAs($this->user);

    $item = ListItem::factory()->create([
        'shopping_list_id' => $this->list->id,
        'added_by_user_id' => $this->user->id,
        'upc' => null,
    ]);

    $response = $this->patch("/items/{$item->id}", [
        'product_name' => 'Updated Product',
        'upc' => '098765432109',
    ]);

    $response->assertRedirect();
    
    $item->refresh();
    expect($item->upc)->toBe('098765432109');
});

it('includes upc in item response', function () {
    $this->actingAs($this->user);

    $item = ListItem::factory()->create([
        'shopping_list_id' => $this->list->id,
        'added_by_user_id' => $this->user->id,
        'upc' => '123456789012',
    ]);

    $response = $this->get("/items/{$item->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => 
        $page->component('Items/Show')
            ->where('item.upc', '123456789012')
    );
});

it('validates upc length', function () {
    $this->actingAs($this->user);

    $response = $this->post("/lists/{$this->list->id}/items", [
        'product_name' => 'Test Product',
        'upc' => '12345678901234567890123456', // Too long
    ]);

    $response->assertSessionHasErrors(['upc']);
});

it('allows null upc', function () {
    $this->actingAs($this->user);

    $response = $this->post("/lists/{$this->list->id}/items", [
        'product_name' => 'Test Product',
        'upc' => null,
    ]);

    $response->assertRedirect();
    
    $item = ListItem::where('shopping_list_id', $this->list->id)->first();
    expect($item)->not->toBeNull();
    expect($item->upc)->toBeNull();
});
