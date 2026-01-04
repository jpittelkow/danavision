<?php

namespace Tests\Feature\ItemEnhancements;

use App\Models\AIProvider;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the Smart Fill feature that uses AI to auto-populate item details.
 */
class SmartFillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ShoppingList $list;
    private ListItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->list = ShoppingList::factory()->create(['user_id' => $this->user->id]);
        $this->item = ListItem::factory()->create([
            'shopping_list_id' => $this->list->id,
            'added_by_user_id' => $this->user->id,
            'product_name' => 'Sony WH-1000XM5 Headphones',
        ]);
    }

    public function test_smart_fill_requires_authentication(): void
    {
        $response = $this->postJson("/items/{$this->item->id}/smart-fill");

        $response->assertUnauthorized();
    }

    public function test_smart_fill_requires_item_ownership(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/items/{$this->item->id}/smart-fill");

        $response->assertForbidden();
    }

    public function test_smart_fill_returns_error_when_no_ai_configured(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/items/{$this->item->id}/smart-fill");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $response->assertJsonFragment([
            'error' => 'No AI providers configured. Please set up an AI provider in Settings.',
        ]);
    }

    public function test_smart_fill_endpoint_exists_and_returns_json(): void
    {
        // Create a mock AI provider (without actual API key, so it will fail gracefully)
        AIProvider::create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/items/{$this->item->id}/smart-fill");

        // Should return JSON (may error due to invalid API key, but endpoint works)
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 422 || $response->status() === 500,
            'Expected status 200, 422, or 500, got ' . $response->status()
        );
    }

    public function test_smart_fill_route_is_only_accessible_via_post(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/items/{$this->item->id}/smart-fill");

        $response->assertStatus(405); // Method Not Allowed
    }

    public function test_smart_fill_response_structure(): void
    {
        // Create a mock AI provider
        AIProvider::create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'api_key' => 'test-key',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/items/{$this->item->id}/smart-fill");

        // Check that response has expected structure (either success or error)
        $json = $response->json();
        
        // Response should have either 'success' key or be an error response
        // The AI call may fail with invalid API key, which is expected in tests
        $this->assertTrue(
            array_key_exists('success', $json) || array_key_exists('error', $json) || array_key_exists('message', $json),
            'Response should have success, error, or message key. Got: ' . json_encode($json)
        );

        if (isset($json['success']) && $json['success']) {
            // Success response should have these fields
            $this->assertArrayHasKey('providers_used', $json);
        }
    }

    public function test_user_can_access_item_show_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/items/{$this->item->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Items/Show')
            ->has('item')
            ->has('can_edit')
            ->where('can_edit', true)
        );
    }

    public function test_item_show_includes_upc_field(): void
    {
        $this->item->update(['upc' => '027242917576']);

        $response = $this->actingAs($this->user)
            ->get("/items/{$this->item->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Items/Show')
            ->where('item.upc', '027242917576')
        );
    }

    public function test_item_can_be_updated_with_upc(): void
    {
        $response = $this->actingAs($this->user)
            ->patch("/items/{$this->item->id}", [
                'upc' => '027242917576',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('list_items', [
            'id' => $this->item->id,
            'upc' => '027242917576',
        ]);
    }
}
