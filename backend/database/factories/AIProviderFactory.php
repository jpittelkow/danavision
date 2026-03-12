<?php

namespace Database\Factories;

use App\Models\AIProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIProvider>
 */
class AIProviderFactory extends Factory
{
    protected $model = AIProvider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['claude', 'openai', 'gemini', 'ollama']),
            'api_key' => fake()->uuid(),
            'model' => 'gpt-4o',
            'is_enabled' => true,
            'is_primary' => false,
            'settings' => [],
        ];
    }

    /**
     * Indicate that this is the primary provider.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that this provider is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Set provider to Claude.
     */
    public function claude(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'claude',
            'model' => 'claude-sonnet-4-20250514',
        ]);
    }

    /**
     * Set provider to OpenAI.
     */
    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);
    }

    /**
     * Set provider to Gemini.
     */
    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'gemini',
            'model' => 'gemini-1.5-pro',
        ]);
    }
}
