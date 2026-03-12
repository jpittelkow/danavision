<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\GroupService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar' => null,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin (in admin group).
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            app(GroupService::class)->ensureDefaultGroupsExist();
            $user->assignGroup('admin');
        });
    }

    /**
     * Indicate that the user has 2FA enabled.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('TESTSECRET12345678'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode([
                'RECOVERY1',
                'RECOVERY2',
                'RECOVERY3',
                'RECOVERY4',
                'RECOVERY5',
            ])),
        ]);
    }
}
