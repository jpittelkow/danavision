<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'notify_on_any_drop' => true,
            'notify_on_threshold' => false,
            'threshold_percent' => null,
            'shop_local' => false,
            'last_refreshed_at' => null,
        ];
    }

    /**
     * Indicate that the list is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate threshold notifications are enabled.
     */
    public function withThreshold(int $percent = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'notify_on_threshold' => true,
            'threshold_percent' => $percent,
        ]);
    }
}
