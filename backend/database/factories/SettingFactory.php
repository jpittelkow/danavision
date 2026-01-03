<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
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
            'key' => fake()->slug(2),
            'value' => fake()->word(),
            'is_encrypted' => false,
        ];
    }

    /**
     * Indicate that the setting is encrypted.
     */
    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
        ]);
    }
}
