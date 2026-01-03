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
        $providers = [AIProvider::PROVIDER_CLAUDE, AIProvider::PROVIDER_OPENAI, AIProvider::PROVIDER_GEMINI];
        $provider = $this->faker->randomElement($providers);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'api_key' => 'sk-test-' . $this->faker->sha256(),
            'model' => AIProvider::$providers[$provider]['default_model'],
            'is_active' => true,
            'is_primary' => false,
            'test_status' => AIProvider::STATUS_UNTESTED,
        ];
    }

    /**
     * Indicate that the provider is Claude.
     */
    public function claude(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AIProvider::PROVIDER_CLAUDE,
            'model' => 'claude-sonnet-4-20250514',
        ]);
    }

    /**
     * Indicate that the provider is OpenAI.
     */
    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AIProvider::PROVIDER_OPENAI,
            'model' => 'gpt-4o',
        ]);
    }

    /**
     * Indicate that the provider is Gemini.
     */
    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AIProvider::PROVIDER_GEMINI,
            'model' => 'gemini-1.5-pro',
        ]);
    }

    /**
     * Indicate that the provider is local/Ollama.
     */
    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AIProvider::PROVIDER_LOCAL,
            'model' => 'llama3.2',
            'base_url' => 'http://localhost:11434',
            'api_key' => null,
        ]);
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
     * Indicate that the provider is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the provider has been tested successfully.
     */
    public function tested(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_status' => AIProvider::STATUS_SUCCESS,
            'last_tested_at' => now(),
        ]);
    }

    /**
     * Indicate that the provider test failed.
     */
    public function testFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'test_status' => AIProvider::STATUS_FAILED,
            'last_tested_at' => now(),
            'test_error' => 'Invalid API key',
        ]);
    }
}
