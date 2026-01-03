<?php

namespace Tests\Feature\ItemEnhancements;

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopLocalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ShoppingList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->list = ShoppingList::factory()->for($this->user)->create([
            'shop_local' => false,
        ]);
    }

    public function test_list_defaults_to_shop_local_false(): void
    {
        $newList = ShoppingList::factory()->for($this->user)->create();
        $this->assertFalse($newList->shop_local);
    }

    public function test_can_update_list_shop_local_setting(): void
    {
        $response = $this->actingAs($this->user)->patch("/lists/{$this->list->id}", [
            'name' => $this->list->name,
            'shop_local' => true,
        ]);

        $response->assertRedirect();
        $this->list->refresh();
        $this->assertTrue($this->list->shop_local);
    }

    public function test_item_inherits_shop_local_from_list_when_null(): void
    {
        $this->list->update(['shop_local' => true]);

        $item = ListItem::factory()->for($this->list)->create([
            'added_by_user_id' => $this->user->id,
            'shop_local' => null,
        ]);

        $this->assertTrue($item->shouldShopLocal());
    }

    public function test_item_override_takes_precedence_over_list(): void
    {
        $this->list->update(['shop_local' => true]);

        $item = ListItem::factory()->for($this->list)->create([
            'added_by_user_id' => $this->user->id,
            'shop_local' => false,
        ]);

        $this->assertFalse($item->shouldShopLocal());
    }

    public function test_item_override_can_enable_local_when_list_disabled(): void
    {
        $this->list->update(['shop_local' => false]);

        $item = ListItem::factory()->for($this->list)->create([
            'added_by_user_id' => $this->user->id,
            'shop_local' => true,
        ]);

        $this->assertTrue($item->shouldShopLocal());
    }

    public function test_can_update_item_shop_local_setting(): void
    {
        $item = ListItem::factory()->for($this->list)->create([
            'added_by_user_id' => $this->user->id,
            'shop_local' => null,
        ]);

        $response = $this->actingAs($this->user)->patch("/items/{$item->id}", [
            'shop_local' => true,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertTrue($item->shop_local);
    }

    public function test_can_reset_item_shop_local_to_inherit(): void
    {
        $item = ListItem::factory()->for($this->list)->create([
            'added_by_user_id' => $this->user->id,
            'shop_local' => true,
        ]);

        $response = $this->actingAs($this->user)->patch("/items/{$item->id}", [
            'shop_local' => null,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertNull($item->shop_local);
    }
}
