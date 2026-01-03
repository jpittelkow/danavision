<?php

namespace Database\Seeders;

use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Dana's account
        $dana = User::factory()->create([
            'name' => 'Dana',
            'email' => 'dana@danavision.app',
            'password' => Hash::make('password'),
        ]);

        // Create a demo list
        $wishlist = ShoppingList::factory()->create([
            'user_id' => $dana->id,
            'name' => 'Kitchen Wishlist',
            'description' => 'Things I want for the kitchen',
        ]);

        // Add some items
        $items = [
            [
                'product_name' => 'KitchenAid Stand Mixer',
                'current_price' => 349.99,
                'previous_price' => 399.99,
                'current_retailer' => 'Amazon',
            ],
            [
                'product_name' => 'Instant Pot Duo 7-in-1',
                'current_price' => 89.99,
                'previous_price' => 99.99,
                'current_retailer' => 'Target',
            ],
            [
                'product_name' => 'Le Creuset Dutch Oven',
                'current_price' => 329.95,
                'previous_price' => 329.95,
                'current_retailer' => 'Williams Sonoma',
            ],
        ];

        foreach ($items as $itemData) {
            $item = ListItem::factory()->create([
                'shopping_list_id' => $wishlist->id,
                'added_by_user_id' => $dana->id,
                'product_name' => $itemData['product_name'],
                'product_query' => $itemData['product_name'],
                'current_price' => $itemData['current_price'],
                'previous_price' => $itemData['previous_price'],
                'lowest_price' => min($itemData['current_price'], $itemData['previous_price']),
                'highest_price' => max($itemData['current_price'], $itemData['previous_price']),
                'current_retailer' => $itemData['current_retailer'],
                'priority' => 'high',
            ]);

            // Add some price history
            PriceHistory::factory()->count(5)->create([
                'list_item_id' => $item->id,
                'retailer' => $itemData['current_retailer'],
            ]);
        }

        // Create a second test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    }
}
