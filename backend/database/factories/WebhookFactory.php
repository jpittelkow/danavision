<?php

namespace Database\Factories;

use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' webhook',
            'url' => fake()->url(),
            'secret' => fake()->sha256(),
            'events' => ['user.created', 'user.updated'],
            'active' => true,
        ];
    }
}
