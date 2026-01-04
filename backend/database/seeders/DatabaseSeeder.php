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
     * 
     * This seeder is production-safe (no Faker dependency).
     */
    public function run(): void
    {
        // Seed default stores for the Store Registry
        $this->call(StoreSeeder::class);
        // Create Dana's account (skip if already exists)
        $dana = User::firstOrCreate(
            ['email' => 'dana@danavision.app'],
            [
                'name' => 'Dana',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create a demo list
        $wishlist = ShoppingList::firstOrCreate(
            ['user_id' => $dana->id, 'name' => 'Kitchen Wishlist'],
            [
                'description' => 'Things I want for the kitchen',
            ]
        );

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
            $item = ListItem::firstOrCreate(
                [
                    'shopping_list_id' => $wishlist->id,
                    'product_name' => $itemData['product_name'],
                ],
                [
                    'added_by_user_id' => $dana->id,
                    'product_query' => $itemData['product_name'],
                    'current_price' => $itemData['current_price'],
                    'previous_price' => $itemData['previous_price'],
                    'lowest_price' => min($itemData['current_price'], $itemData['previous_price']),
                    'highest_price' => max($itemData['current_price'], $itemData['previous_price']),
                    'current_retailer' => $itemData['current_retailer'],
                    'priority' => 'high',
                ]
            );

            // Add price history only if item was just created
            if ($item->wasRecentlyCreated) {
                $basePrice = $itemData['current_price'];
                for ($i = 0; $i < 5; $i++) {
                    PriceHistory::create([
                        'list_item_id' => $item->id,
                        'retailer' => $itemData['current_retailer'],
                        'price' => $basePrice + (($i - 2) * 5), // Vary price slightly
                        'recorded_at' => now()->subDays(5 - $i),
                    ]);
                }
            }
        }

        // Create a second test user (skip if already exists)
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
