<?php

namespace Database\Factories;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListShare>
 */
class ListShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'user_id' => User::factory(),
            'shared_by_user_id' => User::factory(),
            'permission' => 'view',
            'accepted_at' => null,
        ];
    }

    /**
     * Indicate that the share has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Set the permission level.
     */
    public function withPermission(string $permission): static
    {
        return $this->state(fn (array $attributes) => [
            'permission' => $permission,
        ]);
    }
}
