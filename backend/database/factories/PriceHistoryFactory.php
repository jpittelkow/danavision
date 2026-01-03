<?php

namespace Database\Factories;

use App\Models\ListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'list_item_id' => ListItem::factory(),
            'price' => fake()->randomFloat(2, 10, 500),
            'retailer' => fake()->company(),
            'url' => fake()->optional()->url(),
            'in_stock' => true,
            'captured_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'source' => fake()->randomElement(['manual', 'daily_job', 'user_refresh']),
        ];
    }

    /**
     * Set the source.
     */
    public function fromSource(string $source): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => $source,
        ]);
    }
}
