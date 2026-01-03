<?php

use App\Models\User;
use App\Models\ShoppingList;
use App\Models\ListShare;

test('owners can view their lists', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/lists/{$list->id}");

    $response->assertStatus(200);
});

test('non-owners cannot view lists without share', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->get("/lists/{$list->id}");

    $response->assertStatus(403);
});

test('shared users with view permission can view lists', function () {
    $owner = User::factory()->create();
    $sharedUser = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
    
    ListShare::factory()->create([
        'shopping_list_id' => $list->id,
        'user_id' => $sharedUser->id,
        'shared_by_user_id' => $owner->id,
        'permission' => 'view',
        'accepted_at' => now(),
    ]);

    $response = $this->actingAs($sharedUser)->get("/lists/{$list->id}");

    $response->assertStatus(200);
});

test('owners can delete their lists', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete("/lists/{$list->id}");

    $response->assertRedirect('/lists');
    $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
});

test('non-owners cannot delete lists', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($otherUser)->delete("/lists/{$list->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('shopping_lists', ['id' => $list->id]);
});
