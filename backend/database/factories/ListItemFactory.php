<?php

namespace Database\Factories;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListItem>
 */
class ListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentPrice = fake()->randomFloat(2, 10, 500);
        $previousPrice = fake()->optional(0.5)->randomFloat(2, $currentPrice * 0.8, $currentPrice * 1.3);

        return [
            'shopping_list_id' => ShoppingList::factory(),
            'added_by_user_id' => User::factory(),
            'product_name' => fake()->words(3, true),
            'product_query' => fake()->words(2, true),
            'product_image_url' => fake()->optional()->imageUrl(),
            'product_url' => fake()->optional()->url(),
            'notes' => fake()->optional()->sentence(),
            'target_price' => fake()->optional()->randomFloat(2, 5, $currentPrice * 0.8),
            'current_price' => $currentPrice,
            'previous_price' => $previousPrice,
            'lowest_price' => min($currentPrice, $previousPrice ?? $currentPrice),
            'highest_price' => max($currentPrice, $previousPrice ?? $currentPrice),
            'current_retailer' => fake()->company(),
            'in_stock' => true,
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'is_purchased' => false,
            'shop_local' => null,
            'is_generic' => false,
            'unit_of_measure' => null,
            'purchased_at' => null,
            'purchased_price' => null,
            'last_checked_at' => now(),
        ];
    }

    /**
     * Indicate that this is a generic item (sold by weight/volume).
     */
    public function generic(string $unit = 'lb'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_generic' => true,
            'unit_of_measure' => $unit,
            'sku' => null, // Generic items don't have SKUs
        ]);
    }

    /**
     * Indicate that the item is purchased.
     */
    public function purchased(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_purchased' => true,
            'purchased_at' => now(),
            'purchased_price' => $attributes['current_price'] ?? fake()->randomFloat(2, 10, 500),
        ]);
    }

    /**
     * Indicate that the item is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => false,
        ]);
    }

    /**
     * Create an item with a price drop.
     */
    public function withPriceDrop(float $dropPercent = 20): static
    {
        return $this->state(function (array $attributes) use ($dropPercent) {
            $previousPrice = $attributes['current_price'] ?? fake()->randomFloat(2, 50, 500);
            $currentPrice = $previousPrice * (1 - $dropPercent / 100);
            
            return [
                'current_price' => $currentPrice,
                'previous_price' => $previousPrice,
                'lowest_price' => $currentPrice,
            ];
        });
    }

    /**
     * Create an item at all-time low.
     */
    public function atAllTimeLow(): static
    {
        return $this->state(function (array $attributes) {
            $currentPrice = $attributes['current_price'] ?? fake()->randomFloat(2, 10, 100);
            
            return [
                'current_price' => $currentPrice,
                'lowest_price' => $currentPrice,
                'highest_price' => $currentPrice * 1.5,
            ];
        });
    }
}
