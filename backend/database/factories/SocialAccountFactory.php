<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['google', 'github', 'microsoft', 'discord']),
            'provider_id' => fake()->uuid(),
            'provider_token' => fake()->sha256(),
            'provider_refresh_token' => fake()->sha256(),
            'provider_token_expires_at' => now()->addDays(30),
            'avatar' => fake()->imageUrl(),
            'nickname' => fake()->userName(),
        ];
    }

    /**
     * Set provider to Google.
     */
    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'google',
        ]);
    }

    /**
     * Set provider to GitHub.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'github',
        ]);
    }

    /**
     * Set provider to Microsoft.
     */
    public function microsoft(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'microsoft',
        ]);
    }

    /**
     * Set provider to Discord.
     */
    public function discord(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'discord',
        ]);
    }
}
